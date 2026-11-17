<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $dir = dirname(DB_PATH);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            self::$pdo = new PDO('sqlite:' . DB_PATH, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
            self::migrate(self::$pdo);
        }
        return self::$pdo;
    }

    public static function reset(): void { self::$pdo = null; }

    private static function migrate(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                username      TEXT NOT NULL UNIQUE COLLATE NOCASE,
                email         TEXT NOT NULL UNIQUE COLLATE NOCASE,
                password_hash TEXT NOT NULL,
                home_state    TEXT,
                points        INTEGER NOT NULL DEFAULT 0,
                streak        INTEGER NOT NULL DEFAULT 0,
                last_post_day TEXT,
                verified      INTEGER NOT NULL DEFAULT 0,
                created_at    TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS markets (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                state       TEXT NOT NULL,
                name        TEXT NOT NULL COLLATE NOCASE,
                created_by  INTEGER REFERENCES users(id) ON DELETE SET NULL,
                created_at  TEXT NOT NULL DEFAULT (datetime('now')),
                UNIQUE (state, name)
            );

            CREATE TABLE IF NOT EXISTS reports (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                item        TEXT NOT NULL,
                state       TEXT NOT NULL,
                market_id   INTEGER NOT NULL REFERENCES markets(id) ON DELETE CASCADE,
                price       INTEGER NOT NULL,
                photo       TEXT,
                status      TEXT NOT NULL DEFAULT 'live',     -- live | pending | hidden
                confirms    INTEGER NOT NULL DEFAULT 0,
                flags       INTEGER NOT NULL DEFAULT 0,
                verdict     TEXT NOT NULL,                    -- sharp | fair | high | first
                compared_to INTEGER,                          -- typical price at the time of posting
                scope       TEXT NOT NULL DEFAULT 'state',    -- state | national (what it was compared with)
                weight      INTEGER NOT NULL DEFAULT 1,       -- 2 when posted by a Verified Scout
                created_at  TEXT NOT NULL DEFAULT (datetime('now'))
            );
            CREATE INDEX IF NOT EXISTS idx_reports_lookup ON reports(item, state, status, created_at);
            CREATE INDEX IF NOT EXISTS idx_reports_user ON reports(user_id, created_at);

            CREATE TABLE IF NOT EXISTS votes (
                report_id  INTEGER NOT NULL REFERENCES reports(id) ON DELETE CASCADE,
                user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                kind       TEXT NOT NULL,                     -- confirm | flag
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                PRIMARY KEY (report_id, user_id)
            );

            -- Every point earned or lost, so leaderboards can be "this week"
            CREATE TABLE IF NOT EXISTS points (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                amount     INTEGER NOT NULL,
                reason     TEXT NOT NULL,
                state      TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            );
            CREATE INDEX IF NOT EXISTS idx_points_time ON points(created_at);

            CREATE TABLE IF NOT EXISTS badges (
                user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                badge      TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                PRIMARY KEY (user_id, badge)
            );

            CREATE TABLE IF NOT EXISTS alerts (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                item         TEXT NOT NULL,
                state        TEXT NOT NULL,
                below        INTEGER NOT NULL,
                last_sent_at TEXT,
                created_at   TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS hits (
                key        TEXT NOT NULL,
                created_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_hits ON hits(key, created_at);
        SQL);
    }
}
