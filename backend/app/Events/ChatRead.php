<?php

namespace App\Events;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The chat instance.
     *
     * @var \App\Models\Chat
     */
    public $chat;

    /**
     * The user who read the chat.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The timestamp when the chat was read.
     *
     * @var string
     */
    public $readAt;

    /**
     * The number of unread messages before marking as read.
     *
     * @var int
     */
    public $unreadCount;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Chat  $chat
     * @param  \App\Models\User  $user
     * @param  int  $unreadCount
     * @return void
     */
    public function __construct(Chat $chat, User $user, int $unreadCount = 0)
    {
        $this->chat = $chat->only(['id', 'name', 'is_group', 'unread_count']);
        $this->user = $user->only(['id', 'name', 'avatar_url']);
        $this->readAt = now()->toIso8601String();
        $this->unreadCount = $unreadCount;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Broadcast to the chat channel and the user's private channel
        return [
            new PrivateChannel('chat.' . $this->chat['id']),
            new PrivateChannel('user.' . $this->user['id']),
        ];
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'chat.read';
    }
    
    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'chat_id' => $this->chat['id'],
            'user' => $this->user,
            'read_at' => $this->readAt,
            'unread_count' => $this->unreadCount,
        ];
    }
    
    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function broadcastWhen()
    {
        // Only broadcast if we have a valid chat and user
        return !empty($this->chat['id']) && !empty($this->user['id']);
    }
}
