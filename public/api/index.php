<?php
/**
 * JSON API «Реестра» — единственная точка для JavaScript.
 *
 *   GET  api/index.php?action=stats
 *   GET  api/index.php?action=map
 *   GET  api/index.php?action=houses&op=list&q=…
 *   POST api/index.php?action=houses&op=update|delete
 *   (аналогично: outages, appeals)
 */
require_once __DIR__ . '/../../src/bootstrap.php';

if (auth_user() === null) {
    fail('Необходима авторизация.', 401);
}

$action = (string)($_GET['action'] ?? '');
$op     = (string)($_GET['op'] ?? 'list');
$in     = body_input();

try {
    switch ($action) {

        case 'geocode': {
            $query = trim((string)($_GET['q'] ?? ''));
            if (mb_strlen($query) < 3) {
                json_response(['ok' => true, 'data' => []]);
            }
            if (YANDEX_GEOCODER_KEY === '') {
                fail('Не настроен ключ HTTP Геокодера.', 503);
            }

            [[$south, $west], [$north, $east]] = MAP_BOUNDS;
            $url = 'https://geocode-maps.yandex.ru/v1/?' . http_build_query([
                'apikey' => YANDEX_GEOCODER_KEY,
                'format' => 'json',
                'lang' => 'ru_RU',
                'results' => 6,
                'rspn' => 1,
                'bbox' => "$west,$south~$east,$north",
                'geocode' => 'Омск, ' . $query,
            ]);
            $context = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
            $response = @file_get_contents($url, false, $context);
            $decoded = is_string($response) ? json_decode($response, true) : null;
            if (!is_array($decoded)) {
                fail('Не удалось получить ответ от Геокодера Яндекса. Проверьте ключ и подключение.', 502);
            }

            $members = $decoded['response']['GeoObjectCollection']['featureMember'] ?? [];
            $data = [];
            foreach ($members as $member) {
                $object = $member['GeoObject'] ?? [];
                $text = $object['metaDataProperty']['GeocoderMetaData']['text'] ?? '';
                $position = preg_split('/\s+/', trim((string)($object['Point']['pos'] ?? '')));
                if ($text === '' || count($position) !== 2 || !is_numeric($position[0]) || !is_numeric($position[1])) {
                    continue;
                }
                $lng = (float)$position[0];
                $lat = (float)$position[1];
                if ($lat < $south || $lat > $north || $lng < $west || $lng > $east) {
                    continue;
                }
                $data[] = ['text' => $text, 'lat' => $lat, 'lng' => $lng];
            }
            json_response(['ok' => true, 'data' => $data]);
        }

        case 'stats':
            json_response(['ok' => true, 'data' => get_stats()]);
            break;

        case 'map': {
            $data = [
                'outages' => (new OutageRepository())->active(),
                'appeals' => (new AppealRepository())->list(),
            ];
            json_response(['ok' => true, 'data' => $data]);
        }
        break;

        case 'houses': {
            $r = new HouseRepository();
            switch ($op) {
                case 'list':
                    json_response(['ok' => true, 'data' => $r->all($_GET['q'] ?? null)]);

                case 'update': {
                    $id      = (int)($in['id'] ?? 0);
                    $address = trim((string)($in['address'] ?? ''));
                    if ($id <= 0 || mb_strlen($address) < 5) fail('Некорректные данные.');
                    $r->update($id, $address, self_lat($in), self_lng($in));
                    json_response(['ok' => true]);
                }

                case 'delete':
                    $r->delete((int)($in['id'] ?? 0));
                    json_response(['ok' => true]);

                default:
                    fail("Неизвестная операция: $op");
            }
        }
        break;

        case 'outages': {
            $r = new OutageRepository();
            switch ($op) {
                case 'get': {
                    $outage = $r->find((int)($_GET['id'] ?? 0));
                    if ($outage === null) fail('Отключение не найдено.', 404);
                    json_response(['ok' => true, 'data' => $outage]);
                }

                case 'list':
                    json_response(['ok' => true, 'data' => $r->list(
                        $_GET['q'] ?? null,
                        $_GET['type'] ?? 'all',
                        $_GET['status'] ?? 'all'
                    )]);

                case 'create': {
                    $houseId = (int)($in['house_id'] ?? 0);
                    $type    = (string)($in['type'] ?? '');
                    $reason  = trim((string)($in['reason'] ?? ''));
                    $plannedStart = self_datetime($in['planned_start'] ?? null);
                    $actualStart = self_required_datetime($in['actual_start'] ?? null, 'Укажите фактическое время отключения.');
                    $planned = self_datetime($in['planned_end'] ?? null);

                    if ($houseId <= 0) fail('Выберите дом из реестра.');
                    if (!in_array($type, ['hot_water', 'heating'], true)) fail('Выберите тип отключения.');
                    if ((new HouseRepository())->find($houseId) === null) fail('Дом не найден в реестре.', 422);

                    $id = $r->create($houseId, $type, $plannedStart, $actualStart, $reason !== '' ? $reason : null, $planned);
                    json_response(['ok' => true, 'data' => ['id' => $id]], 201);
                }

                case 'update':
                    $id = (int)($in['id'] ?? 0);
                    if ($id <= 0) fail('Не указано ID отключения.');
                    if (isset($in['type']) && !in_array($in['type'], ['hot_water', 'heating'], true)) fail('Выберите тип отключения.');
                    if (isset($in['house_id']) && (new HouseRepository())->find((int)$in['house_id']) === null) fail('Дом не найден в реестре.', 422);
                    if (array_key_exists('planned_start', $in)) {
                        $in['planned_start'] = self_datetime($in['planned_start']);
                    }
                    if (array_key_exists('actual_start', $in)) {
                        $in['actual_start'] = self_required_datetime($in['actual_start'], 'Укажите фактическое время отключения.');
                    }
                    if (array_key_exists('planned_end', $in)) {
                        $in['planned_end'] = self_datetime($in['planned_end']);
                    }
                    if (array_key_exists('house_id', $in) && (int)$in['house_id'] <= 0) fail('Выберите дом из реестра.');
                    $r->update($in);
                    json_response(['ok' => true]);

                case 'delete':
                    $r->delete((int)($in['id'] ?? 0));
                    json_response(['ok' => true]);

                default:
                    fail("Неизвестная операция: $op");
            }
        }
        break;

        case 'appeals': {
            $r = new AppealRepository();
            switch ($op) {
                case 'get': {
                    $appeal = $r->find((int)($_GET['id'] ?? 0));
                    if ($appeal === null) fail('Обращение не найдено.', 404);
                    json_response(['ok' => true, 'data' => $appeal]);
                }

                case 'list':
                    $today = (string)($_GET['today'] ?? '') === '1';
                    $fields = isset($_GET['fields']) ? explode(',', (string)$_GET['fields']) : [];
                    json_response(['ok' => true, 'data' => $r->list(
                        $_GET['q'] ?? null,
                        $today,
                        $fields,
                        (string)($_GET['status'] ?? 'all')
                    )]);

                case 'create': {
                    [$address, $fio, $phone, $err] = self_validate_appeal($in);
                    if ($err) fail($err);
                    [$subject, $type, $err] = self_validate_appeal_details($in);
                    if ($err) fail($err);
                    $callAt = self_required_datetime($in['call_at'] ?? null, 'Укажите дату и время звонка.');
                    $user = auth_user();
                    $operatorQuery = Database::pdo()->prepare('SELECT full_name, is_active FROM accounts WHERE id = ? LIMIT 1');
                    $operatorQuery->execute([(int)($user['id'] ?? 0)]);
                    $operator = $operatorQuery->fetch();
                    if ($operator === false || (int)$operator['is_active'] !== 1) {
                        fail('Учётная запись неактивна. Войдите в систему заново.', 401);
                    }
                    $operatorName = trim((string)$operator['full_name']);
                    if ($operatorName === '') {
                        fail('Заполните ФИО в учётной записи перед регистрацией обращения.', 422);
                    }
                    $id = $r->create($address, $fio, $phone, $subject, $type, $callAt, $operatorName, self_lat($in), self_lng($in));
                    json_response(['ok' => true, 'data' => ['id' => $id]], 201);
                }

                case 'update': {
                    $id = (int)($in['id'] ?? 0);
                    [$address, $fio, $phone, $err] = self_validate_appeal($in);
                    if ($id <= 0) fail('Не указан ID обращения.');
                    if ($err) fail($err);
                    [$subject, $type, $err] = self_validate_appeal_details($in);
                    if ($err) fail($err);
                    $callAt = self_required_datetime($in['call_at'] ?? null, 'Укажите дату и время звонка.');
                    $existing = $r->find($id);
                    if ($existing === null) fail('Обращение не найдено.', 404);
                    $status = in_array($type, ['information', 'false_call'], true)
                        ? 'completed'
                        : (string)$existing['status'];
                    $r->update($id, $address, $fio, $phone, $subject, $type, $status, $callAt, self_lat($in), self_lng($in));
                    json_response(['ok' => true]);
                }

                case 'delete':
                    $r->delete((int)($in['id'] ?? 0));
                    json_response(['ok' => true]);

                case 'set_status':
                    $r->setStatus((int)($in['id'] ?? 0), (string)($in['status'] ?? ''));
                    json_response(['ok' => true]);

                default:
                    fail("Неизвестная операция: $op");
            }
        }
        break;

        default:
            fail('Неизвестное действие. Доступны: stats, map, houses, outages, appeals.', 404);
    }
} catch (PDOException $e) {
    fail('Ошибка БД: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    fail('Ошибка сервера: ' . $e->getMessage(), 500);
}

/* ---------- локальные помощники валидации ---------- */

function self_lat(array $in): ?float
{
    $v = $in['lat'] ?? null;
    return is_numeric($v) ? (float)$v : null;
}

function self_lng(array $in): ?float
{
    $v = $in['lng'] ?? null;
    return is_numeric($v) ? (float)$v : null;
}

/** 'YYYY-MM-DDTHH:MM' / 'YYYY-MM-DD' → 'Y-m-d H:i' | null */
function self_datetime($v): ?string
{
    if (!$v) return null;
    $v = trim((string)$v);
    if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $v)) {
        return self_required_datetime(str_replace(' ', 'T', $v), 'Укажите корректную дату и время.');
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if ($date === false || $date->format('Y-m-d') !== $v) {
            fail('Укажите корректную дату.');
        }
        return $v . ' 23:59';
    }
    fail('Укажите корректную дату и время.');
}

/** Обязательная дата/время из datetime-local или SQL-формата. */
function self_required_datetime($v, string $message): string
{
    $value = trim((string)$v);
    $formats = [
        ['!Y-m-d\TH:i', 'Y-m-d\TH:i'],
        ['!Y-m-d H:i:s', 'Y-m-d H:i:s'],
        ['!Y-m-d H:i', 'Y-m-d H:i'],
    ];
    foreach ($formats as [$format, $expected]) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date !== false && $date->format($expected) === $value) {
            return $date->format('Y-m-d H:i:s');
        }
    }
    fail($message);
}

/** @return array{0:string,1:string,2:string,3:?string} */
function self_validate_appeal(array $in): array
{
    $address = trim((string)($in['address'] ?? ''));
    $fio     = trim((string)($in['fio'] ?? ''));
    $phone   = trim((string)($in['phone'] ?? ''));

    if (mb_strlen($address) < 5)                       return [$address, $fio, $phone, 'Укажите адрес (минимум 5 символов).'];
    if (mb_strlen($fio) < 5 || strpos($fio, ' ') === false) return [$address, $fio, $phone, 'Укажите ФИО полностью, например: Иванов Иван.'];
    if (!preg_match('/^[0-9+()\-\s]{6,20}$/', $phone)) return [$address, $fio, $phone, 'Укажите корректный номер телефона.'];
    return [$address, $fio, $phone, null];
}

/** @return array{0:string,1:string,2:?string} */
function self_validate_appeal_details(array $in): array
{
    $subject = trim((string)($in['subject'] ?? ''));
    $type = (string)($in['type'] ?? '');
    $allowedTypes = ['no_hot_water', 'no_heating', 'information', 'other', 'false_call'];

    if ($subject === '' || mb_strlen($subject) > 10000) {
        return [$subject, $type, 'Заполните состав обращения (не более 10 000 символов).'];
    }
    if (!in_array($type, $allowedTypes, true)) {
        return [$subject, $type, 'Выберите тип обращения.'];
    }
    return [$subject, $type, null];
}
