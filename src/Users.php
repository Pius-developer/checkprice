<?php
declare(strict_types=1);

final class Users
{
    public const BADGES = [
        'first_price'  => ['First price', 'Posted your first price'],
        'pioneer'      => ['Market Pioneer', 'First to post an item from a market'],
        'photographer' => ['Snap Master', 'Added 10 photos'],
        'streak3'      => ['On a roll', '3-day posting streak'],
        'streak7'      => ['Market regular', '7-day posting streak'],
        'verified'     => ['Verified Scout', 'Your prices are confirmed by others. They count double.'],
        'sharp_eye'    => ['Sharp eye', 'Confirmed 20 prices from other scouts'],
    ];

    public function __construct(private PDO $db) {}

    public function find(int $id): ?array
    {
        $s = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public function findByUsername(string $u): ?array
    {
        $s = $this->db->prepare('SELECT * FROM users WHERE username = ?');
        $s->execute([ltrim($u, '@')]);
        return $s->fetch() ?: null;
    }

    public function findByLogin(string $login): ?array
    {
        $s = $this->db->prepare('SELECT * FROM users WHERE email = ? OR username = ?');
        $s->execute([$login, ltrim($login, '@')]);
        return $s->fetch() ?: null;
    }

    public function exists(string $column, string $value): bool
    {
        if (!in_array($column, ['username', 'email'], true)) throw new InvalidArgumentException('Bad column');
        $s = $this->db->prepare("SELECT 1 FROM users WHERE $column = ?");
        $s->execute([$value]);
        return (bool) $s->fetchColumn();
    }

    public function create(string $username, string $email, string $password, ?string $state): int
    {
        $s = $this->db->prepare('INSERT INTO users (username, email, password_hash, home_state) VALUES (?, ?, ?, ?)');
        $s->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $state]);
        return (int) $this->db->lastInsertId();
    }

    public function addPoints(int $userId, int $amount, string $reason, ?string $state = null): void
    {
        $this->db->prepare('INSERT INTO points (user_id, amount, reason, state) VALUES (?, ?, ?, ?)')->execute([$userId, $amount, $reason, $state]);
        $this->db->prepare('UPDATE users SET points = MAX(0, points + ?) WHERE id = ?')->execute([$amount, $userId]);
    }

    /** @return bool true if the badge is new */
    public function award(int $userId, string $badge): bool
    {
        $s = $this->db->prepare('INSERT OR IGNORE INTO badges (user_id, badge) VALUES (?, ?)');
        $s->execute([$userId, $badge]);
        return $s->rowCount() > 0;
    }

    public function badges(int $userId): array
    {
        $s = $this->db->prepare('SELECT badge, created_at FROM badges WHERE user_id = ? ORDER BY created_at');
        $s->execute([$userId]);
        return $s->fetchAll();
    }

    /** Update the posting streak and return the new value. */
    public function touchStreak(array $user, string $today): int
    {
        $streak = Stats::streak($user['last_post_day'], (int) $user['streak'], $today);
        $this->db->prepare('UPDATE users SET streak = ?, last_post_day = ? WHERE id = ?')->execute([$streak, $today, $user['id']]);
        return $streak;
    }

    /**
     * Verified Scout: at least 10 live prices, at least 5 confirmations from others,
     * and at most 1 price hidden by flags. Their prices count double in averages.
     */
    public function refreshVerified(int $userId): bool
    {
        $s = $this->db->prepare(
            "SELECT SUM(status = 'live') AS live, COALESCE(SUM(confirms), 0) AS confirms, SUM(status = 'hidden') AS hidden
             FROM reports WHERE user_id = ?"
        );
        $s->execute([$userId]);
        $r = $s->fetch();
        $ok = (int) $r['live'] >= 10 && (int) $r['confirms'] >= 5 && (int) $r['hidden'] <= 1;
        $this->db->prepare('UPDATE users SET verified = ? WHERE id = ?')->execute([(int) $ok, $userId]);
        if ($ok) $this->award($userId, 'verified');
        return $ok;
    }

    /** Top scouts by points earned in the last $days (optionally in one state). */
    public function leaderboard(int $days = 7, ?string $state = null, int $limit = 20): array
    {
        $sql = "SELECT u.username, u.points AS total, u.verified, SUM(p.amount) AS earned,
                       (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id AND r.created_at > datetime('now', :d1)) AS posts
                FROM points p JOIN users u ON u.id = p.user_id
                WHERE p.created_at > datetime('now', :d2)" . ($state ? ' AND p.state = :s' : '') . "
                GROUP BY u.id HAVING earned > 0 ORDER BY earned DESC, total DESC LIMIT " . (int) $limit;
        $s = $this->db->prepare($sql);
        $params = [':d1' => "-$days days", ':d2' => "-$days days"];
        if ($state) $params[':s'] = $state;
        $s->execute($params);
        return $s->fetchAll();
    }
}
