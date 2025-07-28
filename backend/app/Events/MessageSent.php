<?php

namespace App\Events;

use App\Models\WhatsAppMessage;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The message instance.
     *
     * @var \App\Models\WhatsAppMessage
     */
    public $message;

    /**
     * The user that sent the message.
     *
     * @var \App\Models\User|null
     */
    public $user;

    /**
     * The event type (sent, delivered, read, updated, deleted).
     *
     * @var string
     */
    public $eventType;

    /**
     * Additional data to be sent with the event.
     *
     * @var array
     */
    public $data;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\WhatsAppMessage  $message
     * @param  string  $eventType
     * @param  array  $data
     * @param  \App\Models\User|null  $user
     * @return void
     */
    public function __construct(WhatsAppMessage $message, string $eventType = 'sent', array $data = [], ?User $user = null)
    {
        $this->message = $message;
        $this->eventType = $eventType;
        $this->data = $data;
        $this->user = $user;
        
        // Eager load relationships
        $this->message->load(['senderUser']);
    }

    /**
     * The name of the queue on which to place the broadcasting job.
     *
     * @return string
     */
    public function broadcastQueue()
    {
        return 'broadcasting';
    }
    
    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        // For Reverb, we'll use a public channel for simplicity
        // In production, you might want to use private channels with proper authentication
        return new Channel('chat.test-chat');
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'MessageSent';
    }
    
    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        $messageData = $this->message->toArray();
        
        // Add formatted dates if they exist
        if (isset($this->message->created_at) && $this->message->created_at) {
            $messageData['created_at'] = $this->message->created_at->toDateTimeString();
        }
        if (isset($this->message->updated_at) && $this->message->updated_at) {
            $messageData['updated_at'] = $this->message->updated_at->toDateTimeString();
        }
        if (isset($this->message->timestamp) && $this->message->timestamp) {
            $messageData['timestamp'] = $this->message->timestamp->toDateTimeString();
        }
        
        $userData = null;
        if ($this->user) {
            $userData = [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email ?? null,
                'phone' => $this->user->phone ?? null
            ];
        }
        
        return [
            'message' => $messageData,
            'event_type' => $this->eventType,
            'user' => $userData,
            'data' => $this->data,
            'socket' => null, // This prevents the echo server from sending the message back to the sender
            'timestamp' => now()->toIso8601String()
        ];
    }
    
    /**
     * Determine if this event should be broadcast.
     *
     * @return bool
     */
    public function broadcastWhen()
    {
        // Only broadcast if the message has a chat
        return !empty($this->message->chat);
    }
}
