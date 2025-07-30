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

class UserTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The chat instance.
     *
     * @var \App\Models\Chat
     */
    public $chat;

    /**
     * The user who is typing.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * Whether the user is typing or not.
     *
     * @var bool
     */
    public $isTyping;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Chat  $chat
     * @param  \App\Models\User  $user
     * @param  bool  $isTyping
     * @return void
     */
    public function __construct(Chat $chat, User $user, bool $isTyping = true)
    {
        $this->chat = $chat->only(['id', 'name', 'is_group']);
        $this->user = $user->only(['id', 'name', 'avatar_url']);
        $this->isTyping = $isTyping;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Only broadcast to the specific chat channel
        return new PrivateChannel('chat.' . $this->chat['id']);
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'user.typing';
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
            'is_typing' => $this->isTyping,
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
        // Only broadcast if the user is actually a participant in the chat
        return $this->chat && $this->user;
    }
}
