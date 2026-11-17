<?php
declare(strict_types=1);

define('WEB_ROOT', __DIR__);
require __DIR__ . '/../src/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

/* ===========================================================================
 | Browsing (no login needed)
 * ========================================================================= */

if ($method === 'GET' && $path === '/') {
    $state = current_state();
    $popular = [];
    foreach (['rice-50kg', 'garri-paint', 'beans-paint', 'eggs-crate', 'tomatoes-basket', 'petrol-litre', 'gas-12kg', 'bread-loaf'] as $slug) {
        $now = prices()->summary($slug, $state);
        $old = Stats::summary(prices()->rows($slug, $state, null, null, 30, 30));
        $popular[] = ['item' => Catalog::item($slug), 'sum' => $now, 'change' => Stats::change($old['median'], $now['median'])];
    }
    render('home', [
        'state'   => $state,
        'popular' => $popular,
        'feed'    => prices()->recent(null, null, 8),
        'scouts'  => users()->leaderboard(7, null, 5),
        'totals'  => prices()->totals(),
    ]);
}

if ($method === 'GET' && $path === '/items') {
    $state = current_state();
    $rows = [];
    foreach (Catalog::grouped() as $cat => $items) {
        foreach ($items as $i) {
            $rows[$cat][] = ['item' => $i, 'sum' => prices()->summary($i['slug'], $state)];
        }
    }
    render('items', ['state' => $state, 'rows' => $rows]);
}

// The home page search form submits ?item=...&state=...
if ($method === 'GET' && $path === '/item') {
    $slug = (string) ($_GET['item'] ?? '');
    $st = (string) ($_GET['state'] ?? '');
    redirect(Catalog::item($slug) ? '/item/' . $slug . (Catalog::state($st) ? '?state=' . $st : '') : '/items');
}

if ($method === 'GET' && preg_match('#^/item/([a-z0-9-]+)$#', $path, $m)) {
    $item = Catalog::item($m[1]);
    if (!$item) render('404', [], 404);
    $state = current_state();
    render('item', [
        'item'      => $item,
        'state'     => $state,
        'sum'       => prices()->summary($item['slug'], $state),
        'national'  => prices()->summary($item['slug']),
        'markets'   => prices()->cheapestMarkets($item['slug'], $state),
        'trend'     => prices()->trend($item['slug'], $state),
        'states'    => prices()->byState($item['slug'], 2),
        'feed'      => prices()->recent($item['slug'], $state, 15),
    ]);
}

if ($method === 'GET' && preg_match('#^/r/(\d+)$#', $path, $m)) {
    $r = prices()->find((int) $m[1]);
    if (!$r || $r['status'] === 'hidden') render('404', [], 404);
    $news = $_SESSION['posted'][$r['id']] ?? null;
    unset($_SESSION['posted'][$r['id']]);
    $voted = false;
    if ($me = current_user()) {
        $s = Database::connection()->prepare('SELECT kind FROM votes WHERE report_id = ? AND user_id = ?');
        $s->execute([$r['id'], $me['id']]);
        $voted = $s->fetchColumn() ?: false;
    }
    render('verdict', ['r' => $r, 'news' => $news, 'voted' => $voted]);
}

if ($method === 'GET' && preg_match('#^/r/(\d+)/card\.png$#', $path, $m)) {
    $r = prices()->find((int) $m[1]);
    if (!$r || $r['status'] === 'hidden') render('404', [], 404);
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    readfile(Card::path($r));
    exit;
}

if ($method === 'GET' && preg_match('#^/photo/([a-f0-9]{24}\.jpg)$#', $path, $m) && Photo::validName($m[1])) {
    $file = ROOT . '/data/photos/' . $m[1];
    if (!is_file($file)) render('404', [], 404);
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=2592000, immutable');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}

if ($method === 'GET' && $path === '/basket') {
    render('basket', ['state' => current_state()]);
}

if ($method === 'GET' && $path === '/scouts') {
    $state = isset($_GET['state']) && Catalog::state((string) $_GET['state']) ? (string) $_GET['state'] : null;
    render('scouts', ['state' => $state, 'week' => users()->leaderboard(7, $state, 25), 'all' => users()->leaderboard(3650, $state, 10)]);
}

if ($method === 'GET' && preg_match('#^/@([a-z0-9_]{3,20})$#i', $path, $m)) {
    $user = users()->findByUsername($m[1]);
    if (!$user) render('404', [], 404);
    render('profile', ['user' => $user, 'badges' => users()->badges((int) $user['id']),
                       'feed' => prices()->recent(null, null, 20, (int) $user['id'])]);
}

if ($method === 'GET' && $path === '/report') {
    render('report', ['w' => prices()->weekly()]);
}

/* ===========================================================================
 | JSON for the browser (basket, market autocomplete)
 * ========================================================================= */

if ($method === 'GET' && $path === '/api/markets') {
    $state = (string) ($_GET['state'] ?? '');
    json(Catalog::state($state) ? prices()->markets($state, mb_substr((string) ($_GET['q'] ?? ''), 0, 40)) : []);
}

if ($method === 'GET' && $path === '/api/basket') {
    $state = (string) ($_GET['state'] ?? '');
    if (!Catalog::state($state)) json(['error' => 'Pick a state.'], 422);
    $items = [];
    foreach (array_slice(explode(',', (string) ($_GET['items'] ?? '')), 0, 40) as $pair) {
        [$slug, $qty] = array_pad(explode(':', $pair), 2, 1);
        if (Catalog::item($slug)) $items[$slug] = (int) $qty;
    }
    json(prices()->basket($items, $state));
}

/* ===========================================================================
 | Posting a price
 * ========================================================================= */

if ($path === '/post') {
    $me = current_user();
    if (!$me) {
        flash('Create a free account to post prices and earn points. Browsing stays free without one.');
        $_SESSION['after_login'] = '/post' . (isset($_GET['item']) ? '?item=' . urlencode((string) $_GET['item']) : '');
        redirect('/signup');
    }
    $old = ['item' => (string) ($_GET['item'] ?? ''), 'state' => $me['home_state'] ?: current_state(), 'market' => '', 'price' => ''];
    $errors = [];

    if ($method === 'POST') {
        verify_csrf();
        $old = [
            'item'   => (string) ($_POST['item'] ?? ''),
            'state'  => (string) ($_POST['state'] ?? ''),
            'market' => trim((string) ($_POST['market'] ?? '')),
            'price'  => preg_replace('/[^\d]/', '', (string) ($_POST['price'] ?? '')),
        ];
        if (!Catalog::item($old['item']))  $errors['item'] = 'Pick what you bought.';
        if (!Catalog::state($old['state'])) $errors['state'] = 'Pick your state.';
        if ($old['market'] === '' || mb_strlen($old['market']) > 60) $errors['market'] = 'Where did you buy it? A market, shop or area (up to 60 characters).';
        $price = (int) $old['price'];
        if ($price < 10 || $price > config('max_price')) $errors['price'] = 'Enter the price in naira, like 72500.';
        if (!$errors && !rate_ok('post|' . $me['id'], (int) config('max_posts_per_hour'), 3600)) {
            $errors['price'] = 'You\'ve posted a lot this hour. Take a break and come back soon.';
        }
        $photo = null;
        if (!$errors) {
            $up = Photo::store($_FILES['photo'] ?? ['error' => UPLOAD_ERR_NO_FILE], ROOT . '/data/photos');
            if (!$up['ok']) $errors['photo'] = $up['error'];
            $photo = $up['name'] ?? null;
        }
        if (!$errors) {
            $res = prices()->submit($me, $old['item'], $old['state'], $old['market'], $price, $photo);
            $_SESSION['posted'][$res['id']] = $res;
            $_SESSION['state'] = $old['state'];
            redirect('/r/' . $res['id']);
        }
    }
    render('post', compact('old', 'errors'), $errors ? 422 : 200);
}

if ($method === 'POST' && preg_match('#^/r/(\d+)/(confirm|flag)$#', $path, $m)) {
    $me = require_login();
    verify_csrf();
    flash(prices()->vote((int) $m[1], $me, $m[2]));
    redirect('/r/' . (int) $m[1]);
}

/* ===========================================================================
 | Price alerts
 * ========================================================================= */

if ($path === '/alerts') {
    $me = require_login();
    $errors = [];
    if ($method === 'POST') {
        verify_csrf();
        $item = (string) ($_POST['item'] ?? '');
        $state = (string) ($_POST['state'] ?? '');
        $below = (int) preg_replace('/[^\d]/', '', (string) ($_POST['below'] ?? ''));
        if (!Catalog::item($item) || !Catalog::state($state) || $below < 10) {
            $errors['below'] = 'Pick an item, a state and a target price.';
        } elseif (count(prices()->alerts((int) $me['id'])) >= 10) {
            $errors['below'] = 'You can have up to 10 alerts. Remove one first.';
        } else {
            prices()->addAlert((int) $me['id'], $item, $state, $below);
            flash('Alert set. We\'ll email you when the usual price drops to ' . naira($below) . ' or less.');
            redirect('/alerts');
        }
    }
    render('alerts', ['alerts' => prices()->alerts((int) $me['id']), 'errors' => $errors]);
}

if ($method === 'POST' && preg_match('#^/alerts/(\d+)/delete$#', $path, $m)) {
    $me = require_login();
    verify_csrf();
    prices()->deleteAlert((int) $m[1], (int) $me['id']);
    flash('Alert removed.');
    redirect('/alerts');
}

/* ===========================================================================
 | Accounts
 * ========================================================================= */

if ($path === '/signup') {
    if (current_user()) redirect('/post');
    $old = ['username' => '', 'email' => '', 'state' => current_state()];
    $errors = [];
    if ($method === 'POST') {
        verify_csrf();
        $old = [
            'username' => strtolower(ltrim(trim((string) ($_POST['username'] ?? '')), '@')),
            'email'    => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'state'    => (string) ($_POST['state'] ?? ''),
        ];
        $password = (string) ($_POST['password'] ?? '');
        if (!preg_match('/^[a-z0-9_]{3,20}$/', $old['username'])) $errors['username'] = 'Use 3–20 letters, numbers or underscores.';
        elseif (in_array($old['username'], config('reserved_usernames'), true)) $errors['username'] = 'That name is reserved. Try another.';
        elseif (users()->exists('username', $old['username'])) $errors['username'] = 'That name is taken.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
        elseif (users()->exists('email', $old['email'])) $errors['email'] = 'An account with that email already exists.';
        if (!Catalog::state($old['state'])) $errors['state'] = 'Pick your state.';
        if (strlen($password) < 8) $errors['password'] = 'Use at least 8 characters.';
        if (!$errors && !rate_ok('signup|' . client_ip(), 5, 3600)) $errors['username'] = 'Too many sign-ups from your network. Try again later.';
        if (!$errors) {
            login_user(users()->create($old['username'], $old['email'], $password, $old['state']));
            flash('Welcome, scout! Post your first price to earn 10 points.');
            $to = $_SESSION['after_login'] ?? '/post';
            unset($_SESSION['after_login']);
            redirect($to);
        }
    }
    render('signup', compact('old', 'errors'), $errors ? 422 : 200);
}

if ($path === '/login') {
    if (current_user()) redirect('/post');
    $error = null; $login = '';
    if ($method === 'POST') {
        verify_csrf();
        $login = trim((string) ($_POST['login'] ?? ''));
        if (!rate_ok('login|' . client_ip(), 10, 900)) {
            $error = 'Too many attempts. Wait 15 minutes and try again.';
        } else {
            $user = users()->findByLogin($login);
            if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
                login_user((int) $user['id']);
                $to = $_SESSION['after_login'] ?? '/';
                unset($_SESSION['after_login']);
                redirect($to);
            }
            $error = 'Wrong username/email or password.';
        }
    }
    render('login', compact('error', 'login'), $error ? 422 : 200);
}

if ($method === 'POST' && $path === '/logout') {
    verify_csrf();
    $_SESSION = [];
    session_regenerate_id(true);
    redirect('/');
}

render('404', [], 404);
