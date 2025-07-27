<?php

namespace App\Services;

use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

class WebSocketService
{
    protected Pusher $pusher;

    public function __construct()
    {
        $this->pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'useTLS' => true,
            ]
        );
    }

    public function newMessage(WhatsAppMessage $message): void
    {
        try {
            $this->pusher->trigger(
                'chat.' . $message->chat,
                'new-message',
                [
                    'message' => new \App\Http\Resources\WhatsAppMessageResource($message),
                    'event' => 'new-message',
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send WebSocket notification', [
                'error' => $e->getMessage(),
                'message_id' => $message->id,
            ]);
        }
    }

    public function messageStatusUpdated(WhatsAppMessage $message): void
    {
        try {
            $this->pusher->trigger(
                'chat.' . $message->chat,
                'message-status-updated',
                [
                    'message_id' => $message->id,
                    'status' => $message->status,
                    'read_at' => $message->read_at?->toIso8601String(),
                    'event' => 'message-status-updated',
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send message status update', [
                'error' => $e->getMessage(),
                'message_id' => $message->id,
            ]);
        }
    }

    public function messageReactionUpdated(WhatsAppMessage $message, string $userId, ?string $reaction): void
    {
        try {
            $this->pusher->trigger(
                'chat.' . $message->chat,
                'message-reaction-updated',
                [
                    'message_id' => $message->id,
                    'user_id' => $userId,
                    'reaction' => $reaction,
                    'event' => 'message-reaction-updated',
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send reaction update', [
                'error' => $e->getMessage(),
                'message_id' => $message->id,
            ]);
        }
    }
}
