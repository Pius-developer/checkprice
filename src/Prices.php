<?php
declare(strict_types=1);

/**
 * Everything about price reports: posting, confirming/flagging, and the numbers shown on pages.
 */
final class Prices
{
    public const WINDOW_DAYS = 30;   // "current price" = prices from the last 30 days

    public function __construct(private PDO $db, private Users $users) {}

    // ---- Markets ------------------------------------------------------------

    public function marketId(string $state, string $name, ?int $userId): int
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        $s = $this->db->prepare('SELECT id FROM markets WHERE state = ? AND name = ?');
        $s->execute([$state, $name]);
        if ($id = $s->fetchColumn()) return (int) $id;
        $this->db->prepare('INSERT INTO markets (state, name, created_by) VALUES (?, ?, ?)')->execute([$state, $name, $userId]);
        return (int) $this->db->lastInsertId();
    }

    /** Market names for autocomplete, most used first. */
    public function markets(string $state, string $q = '', int $limit = 12): array
    {
        $s = $this->db->prepare(
            "SELECT m.name, COUNT(r.id) AS n FROM markets m LEFT JOIN reports r ON r.market_id = m.id
             WHERE m.state = ? AND m.name LIKE ? GROUP BY m.id ORDER BY n DESC, m.name LIMIT " . (int) $limit
        );
        $s->execute([$state, '%' . $q . '%']);
        return array_column($s->fetchAll(), 'name');
    }

    // ---- Posting ------------------------------------------------------------

    /**
     * Save a price. Works out the verdict against the current typical price first,
     * holds back extreme outliers, and hands out points and badges.
     *
     * @return array{id:int, verdict:array, status:string, points:int, badges:list<string>, scope:string, compared_to:?int}
     */
    public function submit(array $user, string $item, string $state, string $market, int $price, ?string $photo, ?string $now = null): array
    {
        $now ??= gmdate('Y-m-d H:i:s');
        $this->db->beginTransaction();
        try {
            $marketId = $this->marketId($state, $market, (int) $user['id']);

            // Compare with this state; fall back to the whole country if the state has too little data.
            $local = $this->summary($item, $state, null, $now);
            $scope = 'state';
            $base = $local;
            if ($local['count'] < 3) {
                $national = $this->summary($item, null, null, $now);
                if ($national['count'] >= 3) { $base = $national; $scope = 'national'; }
            }
            $verdict = Stats::verdict($price, $base['median']);
            $status = Stats::isOutlier($price, $base) ? 'pending' : 'live';

            $pioneer = !$this->exists('SELECT 1 FROM reports WHERE item = ? AND market_id = ? AND status != ?', [$item, $marketId, 'hidden']);

            $this->db->prepare(
                'INSERT INTO reports (user_id, item, state, market_id, price, photo, status, verdict, compared_to, scope, weight, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$user['id'], $item, $state, $marketId, $price, $photo, $status, $verdict['kind'],
                        $base['median'], $scope, $user['verified'] ? 2 : 1, $now]);
            $id = (int) $this->db->lastInsertId();

            // Points
            $earned = Stats::POINTS['post'];
            $this->users->addPoints((int) $user['id'], Stats::POINTS['post'], 'post', $state);
            if ($photo) {
                $earned += Stats::POINTS['photo'];
                $this->users->addPoints((int) $user['id'], Stats::POINTS['photo'], 'photo', $state);
            }
            if ($pioneer) {
                $earned += Stats::POINTS['pioneer'];
                $this->users->addPoints((int) $user['id'], Stats::POINTS['pioneer'], 'pioneer', $state);
            }

            // Badges
            $new = [];
            $uid = (int) $user['id'];
            if ($this->users->award($uid, 'first_price')) $new[] = 'first_price';
            if ($pioneer && $this->users->award($uid, 'pioneer')) $new[] = 'pioneer';
            $today = (new DateTimeImmutable($now . ' UTC'))->setTimezone(new DateTimeZone('Africa/Lagos'))->format('Y-m-d');
            $streak = $this->users->touchStreak($user, $today);
            if ($streak >= 3 && $this->users->award($uid, 'streak3')) $new[] = 'streak3';
            if ($streak >= 7 && $this->users->award($uid, 'streak7')) $new[] = 'streak7';
            if ($photo && $this->count('SELECT COUNT(*) FROM reports WHERE user_id = ? AND photo IS NOT NULL', [$uid]) >= 10
                && $this->users->award($uid, 'photographer')) $new[] = 'photographer';

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['id' => $id, 'verdict' => $verdict, 'status' => $status, 'points' => $earned, 'badges' => $new,
                'scope' => $scope, 'compared_to' => $base['median'], 'streak' => $streak];
    }

    // ---- Confirm / flag -------------------------------------------------------

    /** @return string message for the user */
    public function vote(int $reportId, array $voter, string $kind): string
    {
        $r = $this->find($reportId);
        if (!$r || $r['status'] === 'hidden') return 'That price is no longer available.';
        if ((int) $r['user_id'] === (int) $voter['id']) return 'You can\'t confirm or flag your own price.';
        if ($this->exists('SELECT 1 FROM votes WHERE report_id = ? AND user_id = ?', [$reportId, $voter['id']])) {
            return 'You\'ve already responded to this price.';
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO votes (report_id, user_id, kind) VALUES (?, ?, ?)')->execute([$reportId, $voter['id'], $kind]);
            if ($kind === 'confirm') {
                $this->db->prepare("UPDATE reports SET confirms = confirms + 1, status = CASE WHEN status = 'pending' THEN 'live' ELSE status END WHERE id = ?")->execute([$reportId]);
                $this->users->addPoints((int) $r['user_id'], Stats::POINTS['confirmed'], 'confirmed', $r['state']);
                $this->users->addPoints((int) $voter['id'], Stats::POINTS['confirm'], 'confirm', $r['state']);
                if ($this->count("SELECT COUNT(*) FROM votes WHERE user_id = ? AND kind = 'confirm'", [$voter['id']]) >= 20) {
                    $this->users->award((int) $voter['id'], 'sharp_eye');
                }
                $msg = 'Thanks! You confirmed this price. +1 point for you, +3 for the scout.';
            } else {
                $this->db->prepare('UPDATE reports SET flags = flags + 1 WHERE id = ?')->execute([$reportId]);
                $fresh = $this->find($reportId);
                if ($fresh['flags'] >= 3 && $fresh['flags'] > $fresh['confirms']) {
                    $this->db->prepare("UPDATE reports SET status = 'hidden' WHERE id = ?")->execute([$reportId]);
                    $this->users->addPoints((int) $r['user_id'], Stats::POINTS['flagged_hide'], 'flagged', $r['state']);
                }
                $msg = 'Thanks for flagging. Prices with several flags are hidden.';
            }
            $this->users->refreshVerified((int) $r['user_id']);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $msg;
    }

    // ---- Reading ---------------------------------------------------------------

    public function find(int $id): ?array
    {
        $s = $this->db->prepare(
            'SELECT r.*, m.name AS market, u.username, u.verified AS user_verified
             FROM reports r JOIN markets m ON m.id = r.market_id JOIN users u ON u.id = r.user_id WHERE r.id = ?'
        );
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    /** Live prices (price + weight) for an item, optionally in one state/market, within the window. */
    public function rows(string $item, ?string $state = null, ?int $marketId = null, ?string $now = null, int $days = self::WINDOW_DAYS, int $offsetDays = 0): array
    {
        $now ??= gmdate('Y-m-d H:i:s');
        $sql = "SELECT price, weight FROM reports WHERE item = ? AND status = 'live'
                AND created_at > datetime(?, ?) AND created_at <= datetime(?, ?)";
        $params = [$item, $now, '-' . ($days + $offsetDays) . ' days', $now, '-' . $offsetDays . ' days'];
        if ($state) { $sql .= ' AND state = ?'; $params[] = $state; }
        if ($marketId) { $sql .= ' AND market_id = ?'; $params[] = $marketId; }
        $s = $this->db->prepare($sql);
        $s->execute($params);
        return array_map(fn ($r) => ['price' => (int) $r['price'], 'weight' => (int) $r['weight']], $s->fetchAll());
    }

    public function summary(string $item, ?string $state = null, ?int $marketId = null, ?string $now = null): array
    {
        return Stats::summary($this->rows($item, $state, $marketId, $now));
    }

    /** Cheapest markets for an item in a state (by typical price over the window). */
    public function cheapestMarkets(string $item, string $state, int $limit = 5): array
    {
        $s = $this->db->prepare(
            "SELECT m.id, m.name FROM markets m JOIN reports r ON r.market_id = m.id
             WHERE r.item = ? AND r.state = ? AND r.status = 'live' AND r.created_at > datetime('now', ?)
             GROUP BY m.id"
        );
        $s->execute([$item, $state, '-' . self::WINDOW_DAYS . ' days']);
        $out = [];
        foreach ($s->fetchAll() as $m) {
            $sum = $this->summary($item, $state, (int) $m['id']);
            $out[] = ['name' => $m['name'], 'median' => $sum['median'], 'count' => $sum['count']];
        }
        usort($out, fn ($a, $b) => $a['median'] <=> $b['median']);
        return array_slice($out, 0, $limit);
    }

    /** Typical price per state for an item (for "cheapest state" and comparisons). */
    public function byState(string $item, int $minReports = 3): array
    {
        $out = [];
        foreach (array_keys(Catalog::STATES) as $st) {
            $sum = $this->summary($item, $st);
            if ($sum['count'] >= $minReports) $out[$st] = $sum;
        }
        uasort($out, fn ($a, $b) => $a['median'] <=> $b['median']);
        return $out;
    }

    /** Weekly typical prices for the last $weeks weeks, oldest first: [['week' => 'Y-m-d', 'median' => ?int, 'count' => int]]. */
    public function trend(string $item, ?string $state, int $weeks = 12): array
    {
        $out = [];
        for ($w = $weeks - 1; $w >= 0; $w--) {
            $rows = $this->rows($item, $state, null, null, 7, $w * 7);
            $sum = Stats::summary($rows);
            $out[] = ['week' => gmdate('Y-m-d', strtotime('-' . ($w * 7) . ' days')), 'median' => $sum['median'], 'count' => $sum['count']];
        }
        return $out;
    }

    public function recent(?string $item = null, ?string $state = null, int $limit = 20, ?int $userId = null): array
    {
        $sql = "SELECT r.*, m.name AS market, u.username, u.verified AS user_verified
                FROM reports r JOIN markets m ON m.id = r.market_id JOIN users u ON u.id = r.user_id
                WHERE r.status != 'hidden'";
        $params = [];
        if ($item)   { $sql .= ' AND r.item = ?';    $params[] = $item; }
        if ($state)  { $sql .= ' AND r.state = ?';   $params[] = $state; }
        if ($userId) { $sql .= ' AND r.user_id = ?'; $params[] = $userId; }
        $sql .= ' ORDER BY r.created_at DESC, r.id DESC LIMIT ' . (int) $limit;
        $s = $this->db->prepare($sql);
        $s->execute($params);
        return $s->fetchAll();
    }

    /**
     * "My basket": cost of a list of items now vs about three months ago.
     * @param array<string,int> $items slug => quantity
     */
    public function basket(array $items, string $state): array
    {
        $lines = [];
        $now = 0; $then = 0; $complete = true;
        foreach ($items as $slug => $qty) {
            if (!Catalog::item($slug)) continue;
            $qty = max(1, min(99, (int) $qty));
            $scope = 'state';
            $cur = Stats::summary($this->rows($slug, $state));
            if ($cur['count'] < 2) { $cur = Stats::summary($this->rows($slug)); $scope = 'national'; }
            $old = Stats::summary($this->rows($slug, $scope === 'state' ? $state : null, null, null, 30, 75));
            $lines[] = ['item' => $slug, 'label' => Catalog::item($slug)['label'], 'qty' => $qty, 'scope' => $scope,
                        'now' => $cur['median'], 'then' => $old['median'], 'change' => Stats::change($old['median'], $cur['median'])];
            if ($cur['median']) $now += $cur['median'] * $qty; else $complete = false;
            if ($old['median'] && $cur['median']) $then += $old['median'] * $qty;
        }
        $comparable = array_filter($lines, fn ($l) => $l['now'] && $l['then']);
        $nowComparable = array_sum(array_map(fn ($l) => $l['now'] * $l['qty'], $comparable));
        return ['lines' => $lines, 'total' => $now, 'complete' => $complete,
                'change' => $comparable ? Stats::change($then, $nowComparable) : null];
    }

    /** Numbers for the weekly market report. */
    public function weekly(): array
    {
        $movers = [];
        $cheapest = [];
        foreach (array_keys(Catalog::ITEMS) as $slug) {
            $thisWeek = Stats::summary($this->rows($slug, null, null, null, 7));
            $lastWeek = Stats::summary($this->rows($slug, null, null, null, 7, 7));
            if ($thisWeek['count'] >= 3 && $lastWeek['count'] >= 3) {
                $movers[] = ['item' => $slug, 'now' => $thisWeek['median'], 'before' => $lastWeek['median'],
                             'change' => Stats::change($lastWeek['median'], $thisWeek['median'])];
            }
            $states = $this->byState($slug);
            if ($states) {
                $first = array_key_first($states);
                $cheapest[] = ['item' => $slug, 'state' => $first, 'median' => $states[$first]['median']];
            }
        }
        usort($movers, fn ($a, $b) => abs($b['change']) <=> abs($a['change']));
        return [
            'movers'   => array_slice($movers, 0, 8),
            'cheapest' => $cheapest,
            'scouts'   => $this->users->leaderboard(7, null, 5),
            'posts'    => $this->count("SELECT COUNT(*) FROM reports WHERE created_at > datetime('now', '-7 days')", []),
            'people'   => $this->count("SELECT COUNT(DISTINCT user_id) FROM reports WHERE created_at > datetime('now', '-7 days')", []),
            'markets'  => $this->count("SELECT COUNT(DISTINCT market_id) FROM reports WHERE created_at > datetime('now', '-7 days')", []),
        ];
    }

    public function totals(): array
    {
        return [
            'prices'  => $this->count("SELECT COUNT(*) FROM reports WHERE status = 'live'", []),
            'markets' => $this->count('SELECT COUNT(*) FROM markets', []),
            'scouts'  => $this->count('SELECT COUNT(DISTINCT user_id) FROM reports', []),
        ];
    }

    // ---- Alerts -----------------------------------------------------------------

    public function addAlert(int $userId, string $item, string $state, int $below): void
    {
        $this->db->prepare('INSERT INTO alerts (user_id, item, state, below) VALUES (?, ?, ?, ?)')->execute([$userId, $item, $state, $below]);
    }

    public function alerts(int $userId): array
    {
        $s = $this->db->prepare('SELECT * FROM alerts WHERE user_id = ? ORDER BY id DESC');
        $s->execute([$userId]);
        return $s->fetchAll();
    }

    public function deleteAlert(int $id, int $userId): void
    {
        $this->db->prepare('DELETE FROM alerts WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    }

    /** Alerts whose condition is met now and that haven't emailed in the last 7 days. */
    public function dueAlerts(): array
    {
        $s = $this->db->query("SELECT a.*, u.email, u.username FROM alerts a JOIN users u ON u.id = a.user_id
                               WHERE a.last_sent_at IS NULL OR a.last_sent_at < datetime('now', '-7 days')");
        $due = [];
        foreach ($s->fetchAll() as $a) {
            $sum = $this->summary($a['item'], $a['state']);
            if ($sum['count'] >= 2 && $sum['median'] <= (int) $a['below']) {
                $a['median'] = $sum['median'];
                $due[] = $a;
            }
        }
        return $due;
    }

    public function markAlertSent(int $id): void
    {
        $this->db->prepare("UPDATE alerts SET last_sent_at = datetime('now') WHERE id = ?")->execute([$id]);
    }

    // ---- Small helpers -------------------------------------------------------------

    private function exists(string $sql, array $params): bool
    {
        $s = $this->db->prepare($sql);
        $s->execute($params);
        return (bool) $s->fetchColumn();
    }

    private function count(string $sql, array $params): int
    {
        $s = $this->db->prepare($sql);
        $s->execute($params);
        return (int) $s->fetchColumn();
    }
}
