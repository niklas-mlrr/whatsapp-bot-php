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

class UserStoppedTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The chat instance.
     *
     * @var \App\Models\Chat
     */
    public $chat;

    /**
     * The user who stopped typing.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The timestamp when typing stopped.
     *
     * @var string
     */
    public $stoppedAt;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Chat  $chat
     * @param  \App\Models\User  $user
     * @return void
     */
    public function __construct(Chat $chat, User $user)
    {
        $this->chat = $chat->only(['id', 'name', 'is_group']);
        $this->user = $user->only(['id', 'name', 'avatar_url']);
        $this->stoppedAt = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Broadcast to the chat channel, but only to other participants
        $channels = [
            new PrivateChannel('chat.' . $this->chat['id']),
        ];
        
        // If this is a direct message, also notify the recipient directly
        if (!$this->chat['is_group']) {
            $channels[] = new PrivateChannel('user.' . $this->user['id']);
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
        return 'user.stopped_typing';
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
            'stopped_at' => $this->stoppedAt,
            'is_typing' => false,
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
