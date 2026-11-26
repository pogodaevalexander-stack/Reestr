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
define('YANDEX_MAPS_KEY', trim(strval(config()['yandex_maps_api_key'] ?? '76d84887-204c-4c2c-bc82-b0b1bbe77288')));
define('YANDEX_GEOCODER_KEY', trim(strval(config()['yandex_geocoder_api_key'] ?? 'c3256984-03a4-4c60-b80d-e299efbce82d')));
define('MAP_CENTER', config()['map_center']);
define('MAP_BOUNDS', config()['map_bounds']);
define('MAP_ZOOM', (int)config()['map_zoom']);

/** PDO-podключение (singleton). */
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
    $outageCounts = $pdo->query("SELECT COALESCE(SUM(mkd_count), 0) AS mkd, COALESCE(SUM(social_count), 0) AS social, COALESCE(SUM(other_count), 0) AS other FROM outages WHERE is_active = 1 AND type IN ('shutdown','damage')")->fetch();
    return [
        'outages' => (int)$pdo->query("SELECT COUNT(DISTINCT house_id) FROM outages WHERE is_active = 1 AND type IN ('shutdown','damage')")->fetchColumn(),
        'outages_mkd' => (int)$outageCounts['mkd'],
        'outages_social' => (int)$outageCounts['social'],
        'outages_other' => (int)$outageCounts['other'],
        'appeals_in_work' => (int)$pdo->query("SELECT COUNT(*) FROM appeals WHERE status = 'in_work'")->fetchColumn(),
        // Активные благоустройства — для бейджа в боковой панели.
        // Считаем по house_id, как и отключения, — чтобы число значило
        // «сколько адресов в работе», а не «сколько записей».
        'improvements' => (int)$pdo->query("SELECT COUNT(DISTINCT house_id) FROM outages WHERE is_active = 1 AND type = 'improvement'")->fetchColumn(),
    ];
}

/** Записывает действие текущего пользователя в административный журнал. */
function audit_log(string $action, string $entity, ?int $entityId, ?array $before, ?array $after): void
{
    $user = auth_user() ?? [];
    $details = json_encode(['before' => $before, 'after' => $after], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    $st = Database::pdo()->prepare(
        'INSERT INTO audit_log (actor_id, actor_name, action, entity, entity_id, details) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $st->execute([
        isset($user['id']) ? (int)$user['id'] : null,
        (string)($user['full_name'] ?? $user['login'] ?? 'Система'),
        $action, $entity, $entityId, $details,
    ]);
}

/** Ежемесячный накопительный архив записей за скользящие последние три месяца. */
function archive_recent_records(): void
{
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'archive';
    if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) return;
    $month = date('Y-m');
    $lock = @fopen($directory . DIRECTORY_SEPARATOR . '.archive.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) return;
    try {
        $markerPath = $directory . DIRECTORY_SEPARATOR . '.last-run';
        if (is_file($markerPath) && trim((string)file_get_contents($markerPath)) === $month) return;
        $path = $directory . DIRECTORY_SEPARATOR . $month . '.jsonl';
        $seen = [];
        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (is_array($entry) && isset($entry['entity'], $entry['record_id'])) {
                    $seen[$entry['entity'] . ':' . $entry['record_id']] = true;
                }
            }
        }
        $pdo = Database::pdo();
        $linesToAppend = [];
        foreach (['appeals', 'outages'] as $entity) {
            $query = $entity === 'outages'
                ? 'SELECT o.*, h.address FROM outages o JOIN houses h ON h.id = o.house_id WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH) ORDER BY o.created_at, o.id'
                : 'SELECT * FROM appeals WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH) ORDER BY created_at, id';
            $rows = $pdo->query($query)->fetchAll();
            foreach ($rows as $row) {
                $key = $entity . ':' . $row['id'];
                if (isset($seen[$key])) continue;
                $linesToAppend[] = json_encode([
                    'archive_month' => $month,
                    'archived_at' => date(DATE_ATOM),
                    'entity' => $entity,
                    'record_id' => (int)$row['id'],
                    'record' => $row,
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            }
        }
        if ($linesToAppend !== []) {
            $written = @file_put_contents($path, implode(PHP_EOL, $linesToAppend) . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($written === false) return;
        }
        @file_put_contents($markerPath, $month . PHP_EOL, LOCK_EX);
    } catch (Throwable $error) {
        error_log('Archive job failed: ' . $error->getMessage());
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Подстраивает уже установленную БД под новые поля статистики и отключений. */
function ensure_feature_schema(): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $pdo = Database::pdo();
    $columnType = static function (string $table, string $column) use ($pdo): ?string {
        $st = $pdo->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);
        $value = $st->fetchColumn();
        return $value === false ? null : (string)$value;
    };

    $roleType = $columnType('accounts', 'role');
    if ($roleType !== null && strpos($roleType, "'user'") === false) {
        $pdo->exec("ALTER TABLE accounts MODIFY role ENUM('operator','admin','user') NOT NULL DEFAULT 'operator'");
    }

    $outageType = $columnType('outages', 'type');
    if ($outageType !== null) {
        $legacyType = strpos($outageType, 'hot_water') !== false || strpos($outageType, 'heating') !== false;
        if ($legacyType) {
            $pdo->exec("ALTER TABLE outages MODIFY type ENUM('hot_water','heating','shutdown','damage','improvement') NOT NULL DEFAULT 'shutdown'");
            $pdo->exec("UPDATE outages SET type = 'shutdown' WHERE type IN ('hot_water','heating')");
        }
        if ($legacyType || strpos($outageType, 'improvement') === false || strpos($outageType, 'damage') === false) {
            $pdo->exec("ALTER TABLE outages MODIFY type ENUM('shutdown','damage','improvement') NOT NULL DEFAULT 'shutdown'");
        }
    }

    if ($columnType('outages', 'detail_type') === null) {
        $pdo->exec("ALTER TABLE outages ADD COLUMN detail_type ENUM('full_shutdown','supply_without_circulation','damage_without_shutdown') NOT NULL DEFAULT 'full_shutdown' AFTER type");
    }

    foreach ([
        'mkd_count' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'social_count' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'other_count' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'operator_name' => "VARCHAR(255) NOT NULL DEFAULT ''",
    ] as $column => $definition) {
        if ($columnType('outages', $column) === null) {
            $pdo->exec("ALTER TABLE outages ADD COLUMN `$column` $definition");
        }
    }
    if ($columnType('appeals', 'operator_name') === null) {
        $pdo->exec("ALTER TABLE appeals ADD COLUMN operator_name VARCHAR(255) NOT NULL DEFAULT '' AFTER call_at");
    }

    $backfillClosed = false;
    if ($columnType('appeals', 'closed_at') === null) {
        $pdo->exec('ALTER TABLE appeals ADD COLUMN closed_at DATETIME NULL AFTER updated_at');
        $backfillClosed = true;
    }
    if ($columnType('appeals', 'closed_by') === null) {
        $pdo->exec('ALTER TABLE appeals ADD COLUMN closed_by VARCHAR(255) NULL AFTER closed_at');
        $backfillClosed = true;
    }
    if ($backfillClosed) {
        $pdo->exec("UPDATE appeals SET closed_at = updated_at, closed_by = operator_name WHERE status = 'completed' AND closed_at IS NULL");
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS audit_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_id INT UNSIGNED NULL,
            actor_name VARCHAR(255) NOT NULL DEFAULT '',
            action VARCHAR(64) NOT NULL,
            entity VARCHAR(32) NOT NULL,
            entity_id BIGINT UNSIGNED NULL,
            details JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_audit_created (created_at),
            KEY idx_audit_actor (actor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

ensure_feature_schema();
archive_recent_records();
