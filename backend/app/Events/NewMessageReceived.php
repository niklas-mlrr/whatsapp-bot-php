<?php

namespace App\Events;

use App\Models\WhatsAppMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The message instance.
     *
     * @var \App\Models\WhatsAppMessage
     */
    public $message;

    /**
     * The user who sent the message.
     *
     * @var array
     */
    public $sender;

    /**
     * The chat where the message was sent.
     *
     * @var array
     */
    public $chat;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\WhatsAppMessage  $message
     * @return void
     */
    public function __construct(WhatsAppMessage $message)
    {
        $this->message = $message->load(['senderUser', 'chat']);
        
        // Prepare sender data
        $this->sender = $this->message->senderUser 
            ? $this->message->senderUser->only(['id', 'name', 'avatar_url'])
            : ['phone' => $this->message->sender_phone];
        
        // Prepare chat data
        $this->chat = $this->message->chat 
            ? $this->message->chat->only(['id', 'name', 'is_group'])
            : null;
        
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
        // Broadcast to the chat channel and the sender's private channel
        $channels = [
            new PrivateChannel('chat.' . $this->message->chat_id),
            new PrivateChannel('user.' . $this->message->sender_phone),
        ];
        
        // If this is a direct message, also notify the recipient
        if ($this->chat && !$this->chat['is_group']) {
            $channels[] = new PrivateChannel('user.' . $this->message->recipient_phone);
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
        return 'message.received';
    }
    
    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'sender' => $this->sender,
            'chat' => $this->chat,
            'timestamp' => now()->toIso8601String(),
        ];
    }
    
    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function broadcastWhen()
    {
        // Only broadcast if the message has a chat and is not a system message
        return $this->chat && $this->message->type !== 'system';
    }
}
