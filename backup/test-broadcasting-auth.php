<?php

// Simple test script to verify WebSocket authentication endpoint

$appKey = 'whatsapp-bot-key';
$appSecret = 'whatsapp-bot-secret';
$socketId = '123.456';
$channelName = 'private-test-channel';

// Generate the signature the same way Laravel Reverb does
$stringToSign = $socketId . ':' . $channelName;
$signature = hash_hmac('sha256', $stringToSign, $appSecret);
$auth = $appKey . ':' . $signature;

// Output the results
echo "App Key: " . $appKey . "\n";
echo "Socket ID: " . $socketId . "\n";
echo "Channel Name: " . $channelName . "\n";
echo "String to Sign: " . $stringToSign . "\n";
echo "Signature: " . $signature . "\n";
echo "Auth Token: " . $auth . "\n";

// Test the endpoint
$ch = curl_init('http://127.0.0.1:8000/broadcasting/auth');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'socket_id' => $socketId,
    'channel_name' => $channelName
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
]);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo 'cURL error: ' . curl_error($ch);
} else {
    echo "\nHTTP Code: " . $httpCode . "\n";
    echo "Response: " . $result . "\n";
}

curl_close($ch);
