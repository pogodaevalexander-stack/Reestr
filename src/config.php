<?php
/*Базовая конфигурация «Реестра».*/
return [
    // Подключение к MySQL (стандарт XAMPP: пользователь root, пароль пустой)
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'reestr',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    // Создайте отдельные ключи JavaScript API и HTTP Геокодера:
    // https://yandex.ru/maps-api/console/
    'yandex_maps_api_key'     => '76d84887-204c-4c2c-bc82-b0b1bbe77288',
    'yandex_geocoder_api_key' => 'c3256984-03a4-4c60-b80d-e299efbce82d',
    // Карта и геопоиск ограничены Омском. Координаты: [широта, долгота].
    'map_center' => [54.9893, 73.3682],
    'map_bounds' => [[54.82, 73.05], [55.16, 73.75]],
    'map_zoom'   => 11,
];
