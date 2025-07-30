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

class MessageStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The message instance.
     *
     * @var \App\Models\WhatsAppMessage
     */
    public $message;

    /**
     * The new status of the message.
     *
     * @var string
     */
    public $status;

    /**
     * The timestamp when the status was updated.
     *
     * @var string
     */
    public $timestamp;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\WhatsAppMessage  $message
     * @param  string  $status
     * @return void
     */
    public function __construct(WhatsAppMessage $message, string $status)
    {
        $this->message = $message->load('chat');
        $this->status = $status;
        $this->timestamp = now()->toIso8601String();
        
        // Don't include the message content in the broadcast
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
        return [
            new PrivateChannel('chat.' . $this->message->chat_id),
            new PrivateChannel('user.' . $this->message->sender_phone),
        ];
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message.status';
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
            'status' => $this->status,
            'timestamp' => $this->timestamp,
        ];
    }
    
    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function broadcastWhen()
    {
        // Only broadcast if the message has a chat and a valid status
        $validStatuses = ['sent', 'delivered', 'read', 'failed'];
        return $this->message->chat && in_array($this->status, $validStatuses);
    }
}
