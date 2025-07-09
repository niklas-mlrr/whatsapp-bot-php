<?php
// Basic API key protection
/*
$apiKey = "YOUR_SECRET_API_KEY";
if ($_SERVER['HTTP_X_API_KEY'] !== $apiKey) {
    http_response_code(403);
    echo "Forbidden";
    file_put_contents("messages.log", "No the right apiKey", FILE_APPEND);
    exit;
}
*/

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo "Invalid JSON";
    file_put_contents("messages.log", "Invalid JSON\n", FILE_APPEND);
    exit;
}

// Save to log file (or process/store in DB)
$log = sprintf(
    "[%s] postedPackage: %s | title: %s | message: %s | tickerText: %s | arrayOfPersonUris: %s | category: %s | timestamp: %s | dictionaryOfExtras: %s | arrayOfActionLabels: %s | notificationId: %s | notificationChannelId: %s | removalReason: %s\n",
    date('Y-m-d H:i:s'),
    $data['postedPackage'] ?? '',
    $data['title'] ?? '',
    $data['message'] ?? '',
    $data['tickerText'] ?? '',
    $data['arrayOfPersonUris'] ?? '',
    $data['category'] ?? '',
    $data['timestamp'] ?? '',
    $data['dictionaryOfExtras'] ?? '',
    $data['arrayOfActionLabels'] ?? '',
    $data['notificationId'] ?? '',
    $data['notificationChannelId'] ?? '',
    $data['removalReason'] ?? ''
);

file_put_contents("messages.log", $log, FILE_APPEND);

echo json_encode(["status" => "ok"]);



