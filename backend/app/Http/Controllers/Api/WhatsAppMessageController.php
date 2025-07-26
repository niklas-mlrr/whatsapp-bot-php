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

            // If this is an image message, include the full path to the media
            if ($data['type'] === 'image' && !empty($data['media'])) {
                \Log::info('Processing image message', [
                    'media' => $data['media'],
                    'exists' => Storage::exists($data['media'])
                ]);
                
                // If media is a URL, use it directly
                if (filter_var($data['media'], FILTER_VALIDATE_URL)) {
                    $sendPayload['media'] = $data['media'];
                } 
                // If media is a path, read the file and send as base64
                else if (Storage::exists($data['media'])) {
                    // Get the correct path to the file
                    $filePath = Storage::path($data['media']);
                    
                    \Log::info('Attempting to read file', [
                        'storage_path' => $filePath,
                        'relative_path' => $data['media']
                    ]);
                    
                    if (file_exists($filePath)) {
                        $fileContents = file_get_contents($filePath);
                        $base64 = base64_encode($fileContents);
                        $sendPayload['media'] = 'data:' . ($data['mimetype'] ?? 'image/jpeg') . ';base64,' . $base64;
                        \Log::info('Converted local file to base64', [
                            'size' => strlen($base64) . ' bytes',
                            'file' => basename($filePath)
                        ]);
                    } else {
                        \Log::error('File does not exist at path', ['path' => $filePath]);
                        throw new \Exception('File does not exist at path: ' . $filePath);
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
            
            // If we get here, the message was sent successfully
            $message = WhatsAppMessage::findOrFail($message->id);
            return response()->json([
                'status' => 'success',
                'message' => 'Message sent successfully',
                'data' => new WhatsAppMessageResource($message)
            ]);
        } catch (\Exception $e) {
            \Log::error('Error sending message to receiver', [
                'exception' => $e->getMessage(),
                'receiver_url' => $receiverUrl,
                'payload' => $sendPayload
            ]);
            return response()->json(['status' => 'error', 'message' => 'Internal Server Error'], 500);
        }
    }
    
    // GET /api/chats - Get list of unique chat names
    public function chats(): JsonResponse
    {
        try {
            $chats = WhatsAppMessage::query()
                ->select('chat')
                ->distinct()
                ->orderBy('chat')
                ->pluck('chat');
                
            return response()->json(['data' => $chats]);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch chats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch chats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Handle image uploads
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|image|max:10240', // max 10MB
            ]);
            
            // Ensure the uploads directory exists
            $path = $request->file('file')->store('uploads', 'public');
            
            // Get the full URL to the uploaded file
            $url = url(Storage::url($path));
            
            // Make sure the URL is absolute
            if (strpos($url, 'http') !== 0) {
                $url = url($url);
            }
            
            return response()->json([
                'status' => 'success',
                'path' => $path,
                'url' => $url,
                'mimetype' => $request->file('file')->getMimeType()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to upload image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}