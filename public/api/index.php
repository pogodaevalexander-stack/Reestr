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
require __DIR__ . '/../../src/bootstrap.php';

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
                    $planned = self_datetime($in['planned_end'] ?? null);

                    if ($houseId <= 0) fail('Выберите дом из реестра.');
                    if (!in_array($type, ['hot_water', 'heating'], true)) fail('Выберите тип отключения.');
                    if ((new HouseRepository())->find($houseId) === null) fail('Дом не найден в реестре.', 422);

                    $id = $r->create($houseId, $type, $reason !== '' ? $reason : null, $planned, date('Y-m-d H:i:s'));
                    json_response(['ok' => true, 'data' => ['id' => $id]], 201);
                }

                case 'update':
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
                case 'list':
                    $today = (string)($_GET['today'] ?? '') === '1';
                    $fields = isset($_GET['fields']) ? explode(',', (string)$_GET['fields']) : [];
                    json_response(['ok' => true, 'data' => $r->list($_GET['q'] ?? null, $today, $fields)]);

                case 'create': {
                    [$address, $fio, $phone, $err] = self_validate_appeal($in);
                    if ($err) fail($err);
                    $id = $r->create($address, $fio, $phone, self_lat($in), self_lng($in));
                    json_response(['ok' => true, 'data' => ['id' => $id]], 201);
                }

                case 'update': {
                    $id = (int)($in['id'] ?? 0);
                    [$address, $fio, $phone, $err] = self_validate_appeal($in);
                    if ($id <= 0) fail('Не указан ID обращения.');
                    if ($err) fail($err);
                    $r->update($id, $address, $fio, $phone, self_lat($in), self_lng($in));
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
    if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $v)) {
        return str_replace('T', ' ', substr($v, 0, 16));
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return $v . ' 23:59';
    }
    return null;
}

/** @return array{0:string,1:string,2:string,3:?string} */
function self_validate_appeal(array $in): array
{
    $address = trim((string)($in['address'] ?? ''));
    $fio     = trim((string)($in['fio'] ?? ''));
    $phone   = trim((string)($in['phone'] ?? ''));

    if (mb_strlen($address) < 5)                       return [$address, $fio, $phone, 'Укажите адрес (минимум 5 символов).'];
    if (!is_numeric($in['lat'] ?? null) || !is_numeric($in['lng'] ?? null)) return [$address, $fio, $phone, 'Выберите адрес из подсказок Яндекс Карт.'];
    if (mb_strlen($fio) < 5 || strpos($fio, ' ') === false) return [$address, $fio, $phone, 'Укажите ФИО полностью, например: Иванов Иван.'];
    if (!preg_match('/^[0-9+()\-\s]{6,20}$/', $phone)) return [$address, $fio, $phone, 'Укажите корректный номер телефона.'];
    return [$address, $fio, $phone, null];
}
