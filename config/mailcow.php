<?php

return [

    /*
    |--------------------------------------------------------------------------
    | mailcow API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to the mailcow API.
    | The API key should be read-only for security purposes.
    |
    */

    'api_url' => env('MAILCOW_API_URL', 'https://mail.example.com'),

    'api_key' => env('MAILCOW_API_KEY'),

    'verify_ssl' => env('MAILCOW_VERIFY_SSL', true),

    'timeout' => env('MAILCOW_TIMEOUT', 30),

];
