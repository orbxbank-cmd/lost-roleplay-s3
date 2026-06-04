<?php

return [
    'app_name' => 'Lost RoLePLay S03',
    'app_url' => getenv('APP_URL') ?: 'http://localhost/lost-roleplay-shop',
    'timezone' => 'Africa/Casablanca',
    'currency' => 'MAD',
    'currency_symbol' => 'DH',
    'contact_phone' => '0780589707',
    'server_name' => 'Lost Roleplay S03',
    'server_ip' => '151.242.16.244:7777',
    'items_per_page' => 20,
    'session_lifetime' => 86400,
    'debug' => getenv('APP_DEBUG') === 'true',
    'signup_bonus_coins' => 100,
    'coin_packages' => [
        ['coins' => 100, 'price' => 10, 'label' => '100 Coins'],
        ['coins' => 500, 'price' => 40, 'label' => '500 Coins'],
        ['coins' => 1000, 'price' => 70, 'label' => '1000 Coins'],
        ['coins' => 5000, 'price' => 300, 'label' => '5000 Coins'],
    ],
];