<?php

// Using cURL to test the POST endpoint

$url = 'http://127.0.0.1:8000/test-auth';
$data = [
    'socket_id' => '123.456',
    'channel_name' => 'private-test-channel'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($data))
]);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo 'cURL error: ' . curl_error($ch);
} else {
    echo "HTTP Code: " . $httpCode . "\n";
    echo "Response: " . $result . "\n";
}

curl_close($ch);
