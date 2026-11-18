<?php
/**
 * Общая инициализация: конфиг, PDO, автозагрузка, помощники.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '1'); // в продакшене — '0'

/** Конфигурация (запоминается). */
function config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/config.php';
    }
    return $cfg;
}

/** URL-каталог публичной части приложения, определяется по текущему PHP-скрипту. */
function app_base_url(): string
{
    if (PHP_SAPI === 'cli') {
        return '';
    }

    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptName === '') {
        return '';
    }

    $directory = str_replace('\\', '/', dirname($scriptName));
    if (basename($directory) === 'api') {
        $directory = str_replace('\\', '/', dirname($directory));
    }

    return $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');
}

function auth_user(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['auth_user'] = [
        'id'    => (int)($user['id'] ?? 0),
        'login' => trim((string)($user['login'] ?? '')),
        'full_name' => trim((string)($user['full_name'] ?? '')),
        'role'  => (string)($user['role'] ?? 'operator'),
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_auth(): void
{
    $user = auth_user();
    if ($user !== null) {
        $st = Database::pdo()->prepare('SELECT login, full_name, role, is_active FROM accounts WHERE id = ? LIMIT 1');
        $st->execute([(int)$user['id']]);
        $current = $st->fetch();
        if ($current !== false && (int)$current['is_active'] === 1) {
            $_SESSION['auth_user']['login'] = (string)$current['login'];
            $_SESSION['auth_user']['full_name'] = (string)$current['full_name'];
            $_SESSION['auth_user']['role'] = (string)$current['role'];
            return;
        }
        logout_user();
    }

    $next = rawurlencode($_SERVER['REQUEST_URI'] ?? (BASE . '/index.php'));
    header('Location: ' . BASE . '/login.php?next=' . $next);
    exit;
}

/** Проверяет актуальную роль по БД, а не только по данным сессии. */
function require_admin(): void
{
    require_auth();
    $user = auth_user();
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Доступ разрешён только администраторам.');
    }
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function verify_csrf_token($token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals((string)$_SESSION['csrf_token'], $token);
}

define('BASE', app_base_url());
define('YANDEX_MAPS_KEY', trim(strval(config()['yandex_maps_api_key'] ?? '')));
define('YANDEX_GEOCODER_KEY', trim(strval(config()['yandex_geocoder_api_key'] ?? '')));
define('MAP_CENTER', config()['map_center']);
define('MAP_BOUNDS', config()['map_bounds']);
define('MAP_ZOOM', (int)config()['map_zoom']);

/** PDO-подключение (singleton). */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = config()['db'];
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $c['host'], $c['port'], $c['name'], $c['charset']);
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }
}

spl_autoload_register(static function (string $class): void {
    $map = [
        'AccountRepository' => __DIR__ . '/repositories/AccountRepository.php',
        'HouseRepository'   => __DIR__ . '/repositories/HouseRepository.php',
        'OutageRepository'  => __DIR__ . '/repositories/OutageRepository.php',
        'AppealRepository'  => __DIR__ . '/repositories/AppealRepository.php',
    ];
    if (isset($map[$class])) {
        require $map[$class];
    }
});

/* ---------- маленькие помощники ---------- */

function json_response(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $code = 400): void
{
    json_response(['ok' => false, 'error' => $message], $code);
}

/** Данные запроса: JSON-тело или обычная форма (что найдётся). */
function body_input(): array
{
    $raw  = file_get_contents('php://input');
    $json = json_decode((string)$raw, true);
    return is_array($json) ? array_merge($_POST, $json) : $_POST;
}

/** HTML-экранирование. */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Статистика для Главной и бейджей в меню. */
function get_stats(): array
{
    $pdo = Database::pdo();
    return [
        'outages'     => (int)$pdo->query('SELECT COUNT(*) FROM outages WHERE is_active = 1')->fetchColumn(),
        'calls_today' => (int)$pdo->query('SELECT COUNT(*) FROM appeals WHERE DATE(call_at) = CURDATE()')->fetchColumn(),
        'houses'      => (int)$pdo->query('SELECT COUNT(*) FROM houses')->fetchColumn(),
        'appeals'     => (int)$pdo->query('SELECT COUNT(*) FROM appeals')->fetchColumn(),
    ];
}
