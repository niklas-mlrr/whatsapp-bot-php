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
            $sendPayload = [
                'chat' => $data['chat'],
                'type' => $data['type'],
                'content' => $data['content'] ?? '',
                'media' => $data['media'] ?? null,
                'mimetype' => $data['mimetype'] ?? null,
            ];
            $response = Http::timeout(10)->post($receiverUrl, $sendPayload);
            if (!$response->ok()) {
                return response()->json(['status' => 'error', 'message' => 'Failed to send message to WhatsApp', 'details' => $response->body()], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to send message to WhatsApp', 'details' => $e->getMessage()], 500);
        }

        return (new WhatsAppMessageResource($message))->response();
    }

    // POST /api/upload
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|image|max:10240', // max 10MB
        ]);
        $path = $request->file('file')->store('uploads', 'public');
        $url = Storage::url($path);
        return response()->json(['path' => $path, 'url' => $url]);
    }

    // GET /api/chats
    public function chats(): JsonResponse
    {
        $chats = WhatsAppMessage::query()
            ->select('chat')
            ->distinct()
            ->orderBy('chat')
            ->pluck('chat');
        return response()->json(['data' => $chats]);
    }
} 