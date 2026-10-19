<?php
// Put at SITE_ROOT/switch-private/config.php, OUTSIDE public_html. chmod 600.
// Enter the existing DB password directly in Hostinger; never commit the real file.
$dbPassword = ''; // Enter the existing swform user password here in Hostinger only.
return [
    'PUBLIC_ORIGIN'=>'https://switch.theitguys.us',
    'DB_HOST'=>'localhost', 'DB_PORT'=>'3306',
    'DB_NAME'=>'u809002269_swform',
    'DB_USER'=>'u809002269_swform_insert',
    'DB_PASSWORD'=>$dbPassword,
    'DB_SSL_CA'=>'',
    'RATE_DIR'=>__DIR__.'/rate',
    'RATE_SECRET'=>$dbPassword !== '' ? hash_hmac('sha256','switch-review-rate-v1',$dbPassword) : '',
    'TRUSTED_PROXIES'=>'',
];

