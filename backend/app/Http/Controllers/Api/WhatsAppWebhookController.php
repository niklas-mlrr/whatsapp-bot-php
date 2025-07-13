<?php

namespace App\Http\Controllers\Api;

use App\DataTransferObjects\WhatsAppMessageData;
use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsAppMessageRequest;
use App\Services\WhatsAppMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(private readonly WhatsAppMessageService $messageService)
    {
    }

    public function handle(WhatsAppMessageRequest $request): JsonResponse
    {
        try {
            $messageData = WhatsAppMessageData::fromRequest($request);
            $this->messageService->handle($messageData);

            return response()->json([
                'status' => 'ok',
                'message' => 'Daten erfolgreich empfangen',
            ]);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->critical('Ein unerwarteter Fehler im Webhook ist aufgetreten.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ein interner Serverfehler ist aufgetreten.',
            ], 500);
        }
    }
}
