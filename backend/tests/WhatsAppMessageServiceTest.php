<?php

use App\DataTransferObjects\WhatsAppMessageData;
use App\Services\WhatsAppMessageService;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$data = new WhatsAppMessageData([
    'sender' => '4917646765869@s.whatsapp.net',
    'chat' => '4917646765869@s.whatsapp.net',
    'type' => 'text',
    'content' => 'Test message'
]);

$service = $app->make(WhatsAppMessageService::class);
$service->handle($data);

echo "Message processed successfully\n";
