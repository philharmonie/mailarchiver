<?php

return [
    'host' => env('IMAP_HOST', 'localhost'),
    'port' => env('IMAP_PORT', 993),
    'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
    'validate_cert' => env('IMAP_VALIDATE_CERT', true),
    'username' => env('IMAP_USERNAME'),
    'password' => env('IMAP_PASSWORD'),
    'archive_folder' => env('IMAP_ARCHIVE_FOLDER', 'INBOX'),
    'protocol' => 'imap',
];
