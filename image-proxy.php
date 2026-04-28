<?php
require_once __DIR__ . '/includes/db.php';

$src = trim($_GET['src'] ?? '');
if ($src === '' || !preg_match('/^https?:\/\//i', $src)) {
    http_response_code(400);
    exit('Invalid image source.');
}

$context = stream_context_create([
    'http' => [
        'timeout' => 8,
        'follow_location' => 1,
        'user_agent' => 'LearnlyImageProxy/1.0',
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
]);

$data = @file_get_contents($src, false, $context);
if ($data === false) {
    http_response_code(404);
    exit('Image unavailable.');
}

$mime = 'image/jpeg';
if (!empty($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (stripos($header, 'Content-Type:') === 0) {
            $mime = trim(substr($header, strlen('Content-Type:')));
            break;
        }
    }
}

if (!preg_match('/^image\//i', $mime)) {
    http_response_code(415);
    exit('Unsupported media type.');
}

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
echo $data;
