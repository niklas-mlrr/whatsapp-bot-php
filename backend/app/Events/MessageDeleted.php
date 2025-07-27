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

class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The message ID that was deleted.
     *
     * @var int
     */
    public $messageId;

    /**
     * The chat ID where the message was deleted.
     *
     * @var int
     */
    public $chatId;

    /**
     * The user who deleted the message.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * Whether the message was deleted for everyone or just for the user.
     *
     * @var bool
     */
    public $forEveryone;

    /**
     * The timestamp when the message was deleted.
     *
     * @var string
     */
    public $deletedAt;

    /**
     * Create a new event instance.
     *
     * @param  int  $messageId
     * @param  int  $chatId
     * @param  \App\Models\User  $user
     * @param  bool  $forEveryone
     * @return void
     */
    public function __construct(int $messageId, int $chatId, User $user, bool $forEveryone = false)
    {
        $this->messageId = $messageId;
        $this->chatId = $chatId;
        $this->user = $user->only(['id', 'name', 'avatar_url']);
        $this->forEveryone = $forEveryone;
        $this->deletedAt = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $channels = [
            new PrivateChannel('chat.' . $this->chatId),
            new PrivateChannel('user.' . $this->user['id']),
        ];

        // If deleted for everyone, also notify the original sender
        if ($this->forEveryone) {
            $message = WhatsAppMessage::withTrashed()->find($this->messageId);
            if ($message && $message->sender_phone !== $this->user['id']) {
                $channels[] = new PrivateChannel('user.' . $message->sender_phone);
            }
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
        return 'message.deleted';
    }
    
    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'message_id' => $this->messageId,
            'chat_id' => $this->chatId,
            'user' => $this->user,
            'for_everyone' => $this->forEveryone,
            'deleted_at' => $this->deletedAt,
        ];
    }
    
    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function broadcastWhen()
    {
        // Only broadcast if we have valid message and chat IDs
        return !empty($this->messageId) && !empty($this->chatId) && !empty($this->user['id']);
    }
}
