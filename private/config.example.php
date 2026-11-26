<?php
// Private settings for /admin. This folder is blocked from the web by .htaccess.
return [
    // One-time key needed to create your admin password on first visit to /admin.
    // After setup you can leave it; it is only used while no password exists.
    'setup_key' => 'CHANGE-ME-to-a-long-random-string',

    // Apps shown in the admin (slug => name). Slugs must match data-app="" in index.html.
    'apps' => [
        'clickverve'     => 'ClickVerve',
        'mytindahan-pos' => 'MyTindahan POS',
        'mytindahan'     => 'MyTindahan',
        'mybigasan'      => 'MyBigasan',
        'tarsiflow'      => 'tarsiflow',
        'mypickleball'   => 'MyPickleball',
        'filradio'       => 'FilRadio',
        'zendz-tools'    => 'Zendz Tools',
    ],

    'currencies' => ['PHP' => '₱', 'USD' => '$'],
];
