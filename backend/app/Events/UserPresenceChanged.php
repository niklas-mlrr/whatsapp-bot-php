<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPresenceChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The user instance.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The user's new status.
     *
     * @var string
     */
    public $status;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\User  $user
     * @param  string  $status
     * @return void
     */
    public function __construct(User $user, string $status)
    {
        $this->user = $user->only(['id', 'name', 'email', 'avatar_url', 'status', 'last_seen_at']);
        $this->status = $status;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Notify all channels where this user might be present
        $channels = [];
        
        // Notify all chats where this user is a participant
        foreach ($this->user->chats as $chat) {
            $channels[] = new PrivateChannel('chat.' . $chat->id);
        }
        
        // Also notify the user's private channel (for cross-device sync)
        $channels[] = new PrivateChannel('user.' . $this->user->id);
        
        return $channels;
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'user.presence';
    }
    
    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'user' => $this->user,
            'status' => $this->status,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
