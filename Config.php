<?php

$conf = [
    'template' => [
        'default' => ['template', 'Lightning'],
    ],
    'routes' => [
        'cli_only' => [
            'database' => 'Lightning\\CLI\\Database',
            'user' => 'Lightning\\CLI\\User',
            'security' => 'Lightning\\CLI\\Security',
            'gulp' => 'Lightning\\CLI\\Gulp',
        ],
    ],
    'session' => [
        'remember_ttl' => 2592000, // 30 Days
        'password_ttl' => 432000, // 5 Days
        'app_ttl' => 7776000, // 90 Days
        'cookie' => 'session',
        'user_convert' => [
            'tracker_event' => 'tracker_event',
        ]
    ],
    'user' => [
        'login_url' => '/',
        'logout_url' => '/',
    ],
    'ckfinder' => [
        'content' => '/content/',
    ],
    'page' => [
        'modification_date' => true,
    ],
    'redis' => [
        'socket' => 'localhost:6379',
        'default_ttl' => 600,
    ],
    'markup' => [
        'renderers' => [
            'form' => 'Lightning\\View\\Form',
            'template' => 'Lightning\\Tools\\Template',
            'youtube' => 'Lightning\\View\\Video\\YouTube',
            'input' => 'Lightning\\View\\Field',
            'blog' => 'Lightning\\Pages\\Blog',
            'script' => 'Lightning\\View\\Script',
            'iframe' => 'Lightning\\View\\Iframe',
            'social-links' => 'Lightning\\View\\SocialLinks',
        ],
    ],
    'sitemap' => [
        'pages' => 'Lightning\\Model\\Page',
        'blog' => 'Lightning\\Model\\Blog',
    ],
    'language' => 'en_us',
    'template_dir' => 'Source/Templates',
    'temp_dir' => HOME_PATH . '/../tmp',
    'random_engine' => MCRYPT_DEV_URANDOM,
];
