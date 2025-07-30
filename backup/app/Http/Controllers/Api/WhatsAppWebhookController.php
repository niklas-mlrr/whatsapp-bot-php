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
    protected WhatsAppMessageService $messageService;

    public function __construct(WhatsAppMessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    public function handle(WhatsAppMessageRequest $request): JsonResponse
    {
        try {
            Log::channel('whatsapp')->debug('Incoming webhook', [
                'headers' => $request->headers->all(),
                'payload' => $request->except('content')
            ]);

            $messageData = WhatsAppMessageData::fromRequest($request);
            $this->messageService->handle($messageData);

            return response()->json(['status' => 'success']);
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
                'headers' => $request->headers->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'exception' => get_class($e)
            ], 500);
        }
    }
}
