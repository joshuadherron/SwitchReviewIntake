<?php
// Copy OUTSIDE public_html, e.g. /home/ACCOUNT/.switch-review/config.php.
// chmod 600. Point SWITCH_CONFIG_FILE there, or set SWITCH_* environment values.
return [
    'PUBLIC_ORIGIN'=>'https://switchtest.theitguys.us',
    'DB_HOST'=>'localhost', 'DB_PORT'=>'3306',
    'DB_NAME'=>'u809002269_swform_test', 'DB_USER'=>'swform_insert_test',
    'DB_PASSWORD'=>'', 'DB_SSL_CA'=>'',
    'RATE_DIR'=>'/home/ACCOUNT/.switch-review/rate',
    'RATE_SECRET'=>'', // Generate >=32 random characters, never commit the real value.
    'TRUSTED_PROXIES'=>'', // Exact proxy IPs from hosting, never trust arbitrary forwarded headers.
];
