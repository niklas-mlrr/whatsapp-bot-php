<?php

// Simple script to test the authentication endpoint

// First, get a CSRF token
$csrfOptions = [
    'http' => [
        'method' => 'GET',
        'timeout' => 10
    ]
];

$csrfContext = stream_context_create($csrfOptions);
$csrfResult = file_get_contents('http://127.0.0.1:8000/csrf-token', false, $csrfContext);

if ($csrfResult === FALSE) {
    echo "Error: Could not connect to the CSRF token endpoint.\n";
    echo "Please make sure the Laravel development server is running on port 8000.\n";
    exit(1);
}

$csrfData = json_decode($csrfResult, true);
$csrfToken = $csrfData['token'] ?? '';

if (empty($csrfToken)) {
    echo "Error: Could not retrieve CSRF token.\n";
    exit(1);
}

// Now test the authentication endpoint with the CSRF token
$data = [
    'socket_id' => '123.456',
    'channel_name' => 'private-test-channel'
];

$options = [
    'http' => [
        'header' => "Content-Type: application/json\r\nAccept: application/json\r\nX-CSRF-TOKEN: $csrfToken\r\n",
        'method' => 'POST',
        'content' => json_encode($data),
        'timeout' => 10
    ]
];

$context = stream_context_create($options);
$result = file_get_contents('http://127.0.0.1:8000/test-auth', false, $context);

if ($result === FALSE) {
    echo "Error: Could not connect to the authentication endpoint.\n";
    echo "Please make sure the Laravel development server is running on port 8000.\n";
    echo "Error details: " . error_get_last()['message'] . "\n";
} else {
    $response = json_decode($result, true);
    echo "Authentication endpoint response:\n";
    echo json_encode($response, JSON_PRETTY_PRINT);
    echo "\n\n";
    
    if (isset($response['auth'])) {
        echo "SUCCESS: Authentication endpoint is working correctly!\n";
        echo "Auth token: " . $response['auth'] . "\n";
    } else {
        echo "ERROR: Authentication failed.\n";
        if (isset($response['error'])) {
            echo "Error: " . $response['error'] . "\n";
            if (isset($response['message'])) {
                echo "Message: " . $response['message'] . "\n";
            }
        }
    }
}
