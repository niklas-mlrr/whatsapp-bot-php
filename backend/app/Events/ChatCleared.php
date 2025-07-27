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

class ChatCleared implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The chat instance.
     *
     * @var \App\Models\Chat
     */
    public $chat;

    /**
     * The user who cleared the chat.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The timestamp when the chat was cleared.
     *
     * @var string
     */
    public $clearedAt;

    /**
     * Whether to delete the chat after clearing.
     *
     * @var bool
     */
    public $deleteChat;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Chat  $chat
     * @param  \App\Models\User  $user
     * @param  bool  $deleteChat
     * @return void
     */
    public function __construct(Chat $chat, User $user, bool $deleteChat = false)
    {
        $this->chat = $chat->only(['id', 'name', 'is_group']);
        $this->user = $user->only(['id', 'name', 'avatar_url']);
        $this->clearedAt = now()->toIso8601String();
        $this->deleteChat = $deleteChat;
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
        return 'chat.cleared';
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
            'chat_name' => $this->chat['name'],
            'is_group' => $this->chat['is_group'],
            'user' => $this->user,
            'cleared_at' => $this->clearedAt,
            'delete_chat' => $this->deleteChat,
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
