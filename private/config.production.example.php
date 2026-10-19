<?php
// Put at SITE_ROOT/switch-private/config.php, OUTSIDE public_html. chmod 600.
// Enter the existing DB password directly in Hostinger; never commit the real file.
return [
    'PUBLIC_ORIGIN'=>'https://switch.theitguys.us',
    'DB_HOST'=>'localhost', 'DB_PORT'=>'3306',
    'DB_NAME'=>'u809002269_swform',
    'DB_USER'=>'u809002269_swform_insert',
    'DB_PASSWORD'=>'',
    'DB_SSL_CA'=>'',
    'RATE_DIR'=>__DIR__.'/rate',
    'RATE_SECRET'=>'', // Generate a private random value of at least 32 characters.
    'TRUSTED_PROXIES'=>'',
];
