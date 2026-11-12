<?php
/**
 * Общая инициализация: конфиг, PDO, автозагрузка, помощники.
 * Путь: D:\Reestr\src\bootstrap.php
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

function auth_user(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function login_user(array $user): void
{
    $_SESSION['auth_user'] = [
        'id'    => (int)($user['id'] ?? 0),
        'login' => trim((string)($user['login'] ?? '')),
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
    if (auth_user() !== null) {
        return;
    }

    $next = rawurlencode($_SERVER['REQUEST_URI'] ?? (BASE . '/index.php'));
    header('Location: ' . BASE . '/login.php?next=' . $next);
    exit;
}

define('BASE', rtrim(strval(config()['base_url']), '/'));
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
        'calls_today' => (int)$pdo->query('SELECT COUNT(*) FROM appeals WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
        'houses'      => (int)$pdo->query('SELECT COUNT(*) FROM houses')->fetchColumn(),
        'appeals'     => (int)$pdo->query('SELECT COUNT(*) FROM appeals')->fetchColumn(),
    ];
}
