<?php
declare(strict_types=1);

function config(string $key): mixed
{
    return $GLOBALS['config'][$key] ?? null;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---- Services -------------------------------------------------------------

function users(): Users { static $u; return $u ??= new Users(Database::connection()); }
function prices(): Prices { static $p; return $p ??= new Prices(Database::connection(), users()); }

// ---- CSRF -----------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh and try again.');
    }
}

// ---- Auth -----------------------------------------------------------------

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $id = $_SESSION['user_id'] ?? null;
        $user = $id ? users()->find((int) $id) : null;
    }
    return $user;
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('Log in to continue.');
        redirect('/login');
    }
    return $user;
}

/** Simple rate limit: at most $max hits per $seconds for this key. */
function rate_ok(string $key, int $max, int $seconds): bool
{
    $db = Database::connection();
    $key = hash('sha256', $key);
    $s = $db->prepare('SELECT COUNT(*) FROM hits WHERE key = ? AND created_at > ?');
    $s->execute([$key, time() - $seconds]);
    if ((int) $s->fetchColumn() >= $max) {
        return false;
    }
    $db->prepare('INSERT INTO hits (key, created_at) VALUES (?, ?)')->execute([$key, time()]);
    return true;
}

function client_ip(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
}

// ---- Responses ------------------------------------------------------------

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $msg = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $msg;
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function render(string $view, array $data = [], int $status = 200): never
{
    http_response_code($status);
    $data['me']    = current_user();
    $data['flash'] = flash();
    extract($data);
    ob_start();
    require ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require ROOT . '/views/layout.php';
    exit;
}

function json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=60');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function base_url(): string
{
    if (config('app_url')) {
        return rtrim(config('app_url'), '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function host(): string
{
    return preg_replace('#^https?://#', '', base_url());
}

// ---- Formatting -----------------------------------------------------------

function naira(?int $n): string { return Stats::naira($n); }

function ago(?string $utc): string
{
    if (!$utc) return 'never';
    $diff = time() - strtotime($utc . ' UTC');
    return match (true) {
        $diff < 60     => 'just now',
        $diff < 3600   => intdiv($diff, 60) . ' min ago',
        $diff < 86400  => intdiv($diff, 3600) . ' h ago',
        $diff < 604800 => intdiv($diff, 86400) . ' d ago',
        default        => gmdate('j M', strtotime($utc . ' UTC')),
    };
}

/** Selected state: ?state= in the URL, else the last one chosen, else the user's home state, else Lagos. */
function current_state(): string
{
    $q = (string) ($_GET['state'] ?? '');
    if ($q !== '' && Catalog::state($q)) {
        $_SESSION['state'] = $q;
        return $q;
    }
    $me = current_user();
    return $_SESSION['state'] ?? ($me['home_state'] ?? null) ?? 'lagos';
}

/** The verdict in words, Nigerian style. */
function verdict_text(array $r): array
{
    $diff = abs((int) $r['price'] - (int) $r['compared_to']);
    $where = $r['scope'] === 'national' ? 'across Nigeria' : 'in ' . Catalog::state($r['state']);
    return match ($r['verdict']) {
        Stats::SHARP => ['Sharp! 🔥', 'You paid ' . naira($diff) . ' less than the usual price ' . $where . '.'],
        Stats::HIGH  => ['Dem don cheat you 😭', 'You paid ' . naira($diff) . ' more than the usual price ' . $where . '.'],
        Stats::FAIR  => ['Fair price 👌', 'That\'s about what people pay ' . $where . '.'],
        default      => ['Market Pioneer 🚩', 'You\'re the first to post this price ' . $where . '. Others will compare with yours.'],
    };
}

function rank_of(int $points): array { return Stats::rank($points); }
