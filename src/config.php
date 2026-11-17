<?php
declare(strict_types=1);

/*
 | CheckPrice settings. Passwords go in config.local.php (not on GitHub).
 */
$config = [
    'app_name'   => 'CheckPrice',
    'app_url'    => getenv('CHECKPRICE_URL') ?: null,   // null = detect from the request

    'creator'    => 'Ejike Pius',
    'creator_x'  => 'ejike_pius',
    'github_url' => 'https://github.com/pius-developer/checkprice',

    'max_posts_per_hour' => 20,     // per scout
    'max_price'          => 20000000,

    'reserved_usernames' => ['admin', 'checkprice', 'login', 'logout', 'signup', 'post', 'item', 'items', 'basket',
                             'scouts', 'report', 'alerts', 'api', 'photo', 'assets', 'about', 'help', 'r'],

    'mail' => [
        'driver'     => 'log',
        'from_email' => 'hello@ejikepius.dev',
        'from_name'  => 'CheckPrice',
        'reply_to'   => 'hello@ejikepius.dev',
        'host'       => '',
        'port'       => 465,
        'encryption' => 'ssl',
        'username'   => '',
        'password'   => '',
    ],
];

$local = dirname(__DIR__) . '/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}
return $config;
