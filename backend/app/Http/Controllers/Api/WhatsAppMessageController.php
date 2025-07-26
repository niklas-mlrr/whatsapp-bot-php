<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\WhatsAppMessageResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class WhatsAppMessageController extends Controller
{
    // GET /api/messages
    public function index(Request $request): JsonResponse
    {
        $query = WhatsAppMessage::query();

        // Optional: Filtering by sender, chat, type, date, etc.
        if ($request->filled('sender')) {
            $query->where('sender', $request->input('sender'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('chat')) {
            $query->where('chat', $request->input('chat'));
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('sending_time', [$request->input('from'), $request->input('to')]);
        }

        $messages = $query->orderByDesc('sending_time')->paginate(20);
        return WhatsAppMessageResource::collection($messages)->response();
    }

    // GET /api/messages/{id}
    public function show($id): JsonResponse
    {
        $message = WhatsAppMessage::findOrFail($id);
        return (new WhatsAppMessageResource($message))->response();
    }

    // DELETE /api/messages/{id}
    public function destroy($id): JsonResponse
    {
        $message = WhatsAppMessage::findOrFail($id);
        $message->delete();
        return response()->json(['status' => 'success', 'message' => 'Message deleted']);
    }

    // POST /api/messages
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sender' => 'required|string',
            'chat' => 'required|string',
            'type' => 'required|string',
            'content' => 'nullable|string',
            'media' => 'nullable|string',
            'mimetype' => 'nullable|string',
            'sending_time' => 'nullable|date',
        ]);
        if (empty($data['sending_time'])) {
            $data['sending_time'] = now();
        }
        $message = WhatsAppMessage::create($data);

        // Send to receiver
        try {
            $receiverUrl = env('RECEIVER_URL', 'http://localhost:3000/send-message');
            
            \Log::info('Sending message to receiver', [
                'receiver_url' => $receiverUrl,
                'data' => $data
            ]);
            
            $sendPayload = [
                'chat' => $data['chat'],
                'type' => $data['type'],
                'content' => $data['content'] ?? '',
                'media' => null,
                'mimetype' => $data['mimetype'] ?? null,
            ];

            // If this is an image message, include the full URL to the media
            if ($data['type'] === 'image' && !empty($data['media'])) {
                \Log::info('Processing image message', [
                    'media' => $data['media'],
                    'exists' => Storage::exists($data['media'])
                ]);
                
                // If media is a URL, use it directly
                if (filter_var($data['media'], FILTER_VALIDATE_URL)) {
                    $sendPayload['media'] = $data['media'];
                } 
                // If media is a path, convert it to a full URL
                else if (Storage::exists($data['media'])) {
                    $sendPayload['media'] = Storage::url($data['media']);
                    // Make sure the URL is absolute
                    if (strpos($sendPayload['media'], 'http') !== 0) {
                        $baseUrl = rtrim(config('app.url'), '/');
                        $sendPayload['media'] = $baseUrl . '/' . ltrim($sendPayload['media'], '/');
                    }
                }
                
                // Ensure we have a valid media URL
                if (empty($sendPayload['media'])) {
                    return response()->json([
                        'status' => 'error', 
                        'message' => 'Invalid media file',
                        'media_path' => $data['media'],
                        'storage_exists' => Storage::exists($data['media'])
                    ], 400);
                }
                
                \Log::info('Processed media URL', ['url' => $sendPayload['media']]);
            }

            \Log::info('Sending payload to receiver', $sendPayload);
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($receiverUrl, $sendPayload);
            
            if (!$response->successful()) {
                $errorResponse = [
                    'status' => 'error',
                    'message' => 'Failed to send message to WhatsApp',
                    'receiver_status' => $response->status(),
                    'receiver_response' => $response->body(),
                    'receiver_url' => $receiverUrl,
                    'payload' => $sendPayload
                ];
                
                \Log::error('Failed to send message to receiver', $errorResponse);
                
                return response()->json($errorResponse, 500);
            }
        } catch (\Exception $e) {
            \Log::error('Error sending message to receiver', [
                'exception' => $e->getMessage(),
                'receiver_url' => $receiverUrl,
                'payload' => $sendPayload
            ]);
            return response()->json(['status' => 'error', 'message' => 'Internal Server Error'], 500);
        }
    }
}