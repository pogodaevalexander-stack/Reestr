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
    'yandex_maps_api_key'     => '',
    'yandex_geocoder_api_key' => '',
    // Карта и геопоиск ограничены Омском. Координаты: [широта, долгота].
    'map_center' => [54.9893, 73.3682],
    'map_bounds' => [[54.82, 73.05], [55.16, 73.75]],
    'map_zoom'   => 11,
];
