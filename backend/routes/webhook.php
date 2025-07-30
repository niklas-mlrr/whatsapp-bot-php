<?php

use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Services\WhatsAppMessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

Route::post('/whatsapp/webhook', function (Request $request) {
    Log::channel('whatsapp')->debug('Webhook request received', [
        'headers' => $request->headers->all(),
        'ip' => $request->ip()
    ]);

    if ($request->header('X-API-KEY') !== env('WHATSAPP_API_KEY')) {
        return response()->json(['error' => 'Invalid API key'], 403);
    }
    
    try {
        return (new WhatsAppWebhookController(app(WhatsAppMessageService::class)))
            ->handle($request);
    } catch (\Throwable $e) {
        Log::channel('whatsapp')->error('Webhook route failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'error' => $e->getMessage(),
            'exception' => get_class($e)
        ], 500);
    }
})->name('whatsapp.webhook');
