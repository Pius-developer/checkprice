<?php
declare(strict_types=1);

/*
 | CheckPrice tests. Run:  php tests/run.php
 */
define('DB_PATH', sys_get_temp_dir() . '/checkprice-test-' . getmypid() . '.sqlite');
define('PHOTO_TEST', true);
require dirname(__DIR__) . '/src/bootstrap.php';

$passed = 0; $failed = 0;
function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try { $fn(); $passed++; echo "  ✓ $name\n"; }
    catch (Throwable $e) { $failed++; echo "  ✗ $name\n    " . $e->getMessage() . "\n"; }
}
function eq($a, $b, string $msg = ''): void
{
    if ($a !== $b) throw new Exception(($msg ? "$msg: " : '') . 'expected ' . var_export($b, true) . ', got ' . var_export($a, true));
}
$rows = fn (array $prices, int $w = 1) => array_map(fn ($p) => ['price' => $p, 'weight' => $w], $prices);

echo "\nThe maths\n";
test('median of odd and even lists', function () use ($rows) {
    eq(Stats::summary($rows([10, 30, 20]))['median'], 20);
    eq(Stats::summary($rows([10, 20, 30, 40]))['median'], 25);
});
test('fair range is the middle half', function () use ($rows) {
    $s = Stats::summary($rows([100, 200, 300, 400, 500, 600, 700, 800]));
    eq([$s['low'], $s['high']], [250, 650]);
});
test('Verified Scout prices count double', function () {
    $s = Stats::summary([['price' => 100, 'weight' => 1], ['price' => 200, 'weight' => 1], ['price' => 300, 'weight' => 2]]);
    eq($s['median'], 250);
});
test('verdicts: sharp, fair, high, first', function () {
    eq(Stats::verdict(8000, 10000)['kind'], Stats::SHARP);
    eq(Stats::verdict(9500, 10000)['kind'], Stats::FAIR);
    eq(Stats::verdict(10900, 10000)['kind'], Stats::FAIR);
    eq(Stats::verdict(11500, 10000)['kind'], Stats::HIGH);
    eq(Stats::verdict(500, null)['kind'], Stats::FIRST);
});
test('extreme prices wait for confirmation (only once there is enough data)', function () use ($rows) {
    $few = Stats::summary($rows([1000, 1100]));
    $many = Stats::summary($rows([1000, 1050, 1100, 1000, 980]));
    eq(Stats::isOutlier(9000, $few), false);
    eq(Stats::isOutlier(9000, $many), true);
    eq(Stats::isOutlier(300, $many), true);
    eq(Stats::isOutlier(1500, $many), false);
});
test('ranks and progress', function () {
    eq(Stats::rank(0)['name'], 'Market Newbie');
    eq(Stats::rank(50)['name'], 'Price Hunter');
    eq(Stats::rank(700)['name'], 'Oga Market');
    eq(Stats::rank(120)['to_next'], 80);
    eq(Stats::rank(5000)['next'], null);
});
test('streaks: same day, next day, gap', function () {
    eq(Stats::streak('2026-11-16', 3, '2026-11-16'), 3);
    eq(Stats::streak('2026-11-15', 3, '2026-11-16'), 4);
    eq(Stats::streak('2026-11-10', 3, '2026-11-16'), 1);
    eq(Stats::streak(null, 0, '2026-11-16'), 1);
});
test('naira formatting', fn () => eq(Stats::naira(72500), '₦72,500'));

echo "\nPosting, points and badges\n";
$u = users();
$a = $u->create('ada', 'ada@example.com', 'secret123', 'lagos');
$b = $u->create('bayo', 'bayo@example.com', 'secret123', 'lagos');
$c = $u->create('chidi', 'chidi@example.com', 'secret123', 'lagos');
$d = $u->create('dayo', 'dayo@example.com', 'secret123', 'lagos');
$post = fn (int $uid, int $price, string $market = 'Mile 12', string $item = 'rice-50kg', ?string $photo = null, ?string $now = null)
    => prices()->submit($u->find($uid), $item, 'lagos', $market, $price, $photo, $now);

test('first price ever is a Pioneer with bonus points', function () use ($post, $u, $a) {
    $r = $post($a, 72000);
    eq($r['verdict']['kind'], Stats::FIRST);
    eq($r['points'], 30);
    eq(in_array('pioneer', $r['badges'], true), true);
    eq((int) $u->find($a)['points'], 30);
});
test('same market again: no pioneer bonus', function () use ($post, $b) {
    eq($post($b, 74000)['points'], 10);
});
test('a photo earns 5 more points', function () use ($post, $c) {
    eq($post($c, 73000, 'Mile 12', 'rice-50kg', 'abc.jpg')['points'], 15);
});
test('verdict compares with the typical price', function () use ($post, $d) {
    $r = $post($d, 85000);
    eq($r['verdict']['kind'], Stats::HIGH);
    eq($r['compared_to'], 73000);
});
test('market names are matched without caring about case or spaces', function () use ($post, $a) {
    $post($a, 71000, '  mile   12 ');
    eq((int) Database::connection()->query("SELECT COUNT(*) FROM markets WHERE state='lagos'")->fetchColumn(), 1);
});
test('an absurd price is held until someone confirms it', function () use ($post, $b, $c, $u) {
    $r = $post($b, 900000);
    eq($r['status'], 'pending');
    eq(prices()->summary('rice-50kg', 'lagos')['max'] < 900000, true);
    prices()->vote($r['id'], $u->find($c), 'confirm');
    eq(prices()->find($r['id'])['status'], 'live');
});
test('you cannot confirm your own price, or vote twice', function () use ($u, $a, $b) {
    $id = (int) Database::connection()->query("SELECT id FROM reports WHERE user_id = $a LIMIT 1")->fetchColumn();
    eq(str_contains(prices()->vote($id, $u->find($a), 'confirm'), 'own'), true);
    prices()->vote($id, $u->find($b), 'confirm');
    eq(str_contains(prices()->vote($id, $u->find($b), 'confirm'), 'already'), true);
});
test('three flags hide a price and cost the scout points', function () use ($post, $u, $a, $b, $c, $d) {
    $r = $post($a, 50000, 'Oyingbo');
    $before = (int) $u->find($a)['points'];
    foreach ([$b, $c, $d] as $voter) prices()->vote($r['id'], $u->find($voter), 'flag');
    eq(prices()->find($r['id'])['status'], 'hidden');
    eq((int) $u->find($a)['points'], $before - 15);
});
test('national comparison when a state has little data', function () use ($u, $a) {
    foreach (['kano', 'kano', 'oyo'] as $i => $st) prices()->submit($u->find($a), 'eggs-crate', $st, 'Market ' . $i, 5000 + $i * 100, null);
    $r = prices()->submit($u->find($a), 'eggs-crate', 'enugu', 'Ogbete', 4000, null);
    eq($r['scope'], 'national');
    eq($r['verdict']['kind'], Stats::SHARP);
});
test('streak badge after 3 days in a row', function () use ($u, $d) {
    $user = $u->find($d);
    foreach (['2026-11-13 09:00:00', '2026-11-14 09:00:00', '2026-11-15 09:00:00'] as $t) {
        $r = prices()->submit($u->find($d), 'bread-loaf', 'lagos', 'Yaba', 1500, null, $t);
    }
    eq(in_array('streak3', $r['badges'], true), true);
    eq((int) $u->find($d)['streak'], 3);
});
test('cheapest markets are sorted by typical price', function () use ($u, $a, $b) {
    prices()->submit($u->find($a), 'garri-paint', 'lagos', 'Ketu', 3000, null);
    prices()->submit($u->find($b), 'garri-paint', 'lagos', 'Oyingbo', 2500, null);
    eq(array_column(prices()->cheapestMarkets('garri-paint', 'lagos'), 'name'), ['Oyingbo', 'Ketu']);
});
test('basket: total and change vs three months ago', function () use ($u, $a, $b) {
    $old = gmdate('Y-m-d H:i:s', strtotime('-90 days'));
    prices()->submit($u->find($a), 'sugar-1kg', 'lagos', 'Ketu', 1000, null, $old);
    prices()->submit($u->find($b), 'sugar-1kg', 'lagos', 'Ketu', 1000, null, $old);
    prices()->submit($u->find($a), 'sugar-1kg', 'lagos', 'Ketu', 1200, null);
    prices()->submit($u->find($b), 'sugar-1kg', 'lagos', 'Ketu', 1200, null);
    $basket = prices()->basket(['sugar-1kg' => 2], 'lagos');
    eq($basket['total'], 2400);
    eq($basket['change'], 20.0);
});
test('leaderboard ranks by points earned this week', function () use ($u) {
    $board = $u->leaderboard(7, 'lagos');
    eq($board[0]['username'], 'ada');
});
test('price alerts fire when the typical price drops below the target', function () use ($a) {
    prices()->addAlert($a, 'garri-paint', 'lagos', 3000);
    prices()->addAlert($a, 'garri-paint', 'lagos', 1000);
    $due = prices()->dueAlerts();
    eq(count($due), 1);
    prices()->markAlertSent((int) $due[0]['id']);
    eq(count(prices()->dueAlerts()), 0);
});

echo "\nPhotos\n";
test('photos are shrunk, re-saved as JPEG and lose their hidden data', function () {
    $src = imagecreatetruecolor(3000, 2000);
    imagefill($src, 0, 0, imagecolorallocate($src, 200, 50, 50));
    $tmp = tempnam(sys_get_temp_dir(), 'ph') . '.jpg';
    imagejpeg($src, $tmp, 90);
    // add a fake "GPS" comment to prove it's removed
    file_put_contents($tmp, str_replace("\xFF\xDB", "\xFF\xFE\x00\x11GPS:6.5244,3.3792\xFF\xDB", file_get_contents($tmp)));
    $dir = sys_get_temp_dir() . '/cp-photos-' . getmypid();
    $res = Photo::store(['error' => UPLOAD_ERR_OK, 'tmp_name' => $tmp, 'size' => filesize($tmp)], $dir);
    eq($res['ok'], true);
    [$w, $h] = getimagesize("$dir/{$res['name']}");
    eq([$w, $h], [1200, 800]);
    eq(str_contains(file_get_contents("$dir/{$res['name']}"), 'GPS:'), false);
    array_map('unlink', glob("$dir/*")); rmdir($dir); unlink($tmp);
});
test('a text file pretending to be a photo is rejected', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'ph');
    file_put_contents($tmp, '<?php echo "hacked";');
    $res = Photo::store(['error' => UPLOAD_ERR_OK, 'tmp_name' => $tmp, 'size' => 30], sys_get_temp_dir());
    eq($res['ok'], false);
    unlink($tmp);
});
test('photo names are checked before serving', function () {
    eq(Photo::validName('0123456789abcdef01234567.jpg'), true);
    eq(Photo::validName('../config.php'), false);
});

echo "\nShare card\n";
test('the verdict card is a 1200×630 PNG', function () {
    $id = (int) Database::connection()->query("SELECT id FROM reports WHERE verdict = 'high' LIMIT 1")->fetchColumn();
    $img = Card::draw(prices()->find($id));
    eq([imagesx($img), imagesy($img)], [1200, 630]);
});

@unlink(DB_PATH); @unlink(DB_PATH . '-wal'); @unlink(DB_PATH . '-shm');
echo "\n" . ($failed ? "✗ $failed failed, $passed passed\n" : "✓ All $passed tests passed\n");
exit($failed ? 1 : 0);
