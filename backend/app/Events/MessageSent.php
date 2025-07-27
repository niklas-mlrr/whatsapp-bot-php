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
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Notify all participants of the chat
        $channels = [new PrivateChannel('chat.' . $this->message->chat)];
        
        // Also notify the sender on their private channel
        if ($this->message->sender) {
            $channels[] = new PrivateChannel('user.' . $this->message->sender);
        }
        
        return $channels;
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message.' . $this->eventType;
    }
    
    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'message' => array_merge(
                $this->message->toArray(),
                ['event_type' => $this->eventType]
            ),
            'chat_id' => $this->message->chat,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar_url,
            ] : null,
            'sender' => $this->message->senderUser ? [
                'id' => $this->message->senderUser->id,
                'name' => $this->message->senderUser->name,
                'avatar' => $this->message->senderUser->avatar_url,
            ] : [
                'phone' => $this->message->sender,
            ],
            'data' => $this->data,
            'timestamp' => now()->toIso8601String(),
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
