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

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The message instance.
     *
     * @var \App\Models\WhatsAppMessage
     */
    public $message;

    /**
     * The user who read the message.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The timestamp when the message was read.
     *
     * @var string
     */
    public $readAt;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\WhatsAppMessage  $message
     * @param  \App\Models\User  $user
     * @return void
     */
    public function __construct(WhatsAppMessage $message, User $user)
    {
        $this->message = $message->load(['chat']);
        $this->user = $user->only(['id', 'name', 'avatar_url']);
        $this->readAt = now()->toIso8601String();
        
        // Don't include the full message content in the broadcast
        $this->message->makeHidden(['content', 'media_url', 'media_type']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Broadcast to the chat channel and the message sender's private channel
        $channels = [
            new PrivateChannel('chat.' . $this->message->chat_id),
            new PrivateChannel('user.' . $this->message->sender_phone),
        ];
        
        // Also notify the reader (for cross-device sync)
        $channels[] = new PrivateChannel('user.' . $this->user['id']);
        
        return $channels;
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message.read';
    }
    
    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'message_id' => $this->message->id,
            'chat_id' => $this->message->chat_id,
            'user' => $this->user,
            'read_at' => $this->readAt,
        ];
    }
    
    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function broadcastWhen()
    {
        // Only broadcast if we have a valid message and user
        return !empty($this->message->id) && !empty($this->user['id']);
    }
}
