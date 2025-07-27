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

class UserOnlineStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The user instance.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The user's new online status.
     *
     * @var bool
     */
    public $isOnline;

    /**
     * The timestamp when the status changed.
     *
     * @var string
     */
    public $changedAt;

    /**
     * The user's last seen timestamp if going offline.
     *
     * @var string|null
     */
    public $lastSeenAt;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\User  $user
     * @param  bool  $isOnline
     * @param  string|null  $lastSeenAt
     * @return void
     */
    public function __construct(User $user, bool $isOnline = true, ?string $lastSeenAt = null)
    {
        $this->user = $user->only(['id', 'name', 'avatar_url', 'status']);
        $this->isOnline = $isOnline;
        $this->changedAt = now()->toIso8601String();
        $this->lastSeenAt = $lastSeenAt;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Broadcast to all channels where this user is present
        $channels = [
            new PrivateChannel('user.' . $this->user['id']),
        ];

        // If the user is in any chats, broadcast to those as well
        // Note: This assumes the user model has a chats relationship
        if (method_exists(User::class, 'chats')) {
            $user = User::find($this->user['id']);
            if ($user) {
                foreach ($user->chats as $chat) {
                    $channels[] = new PrivateChannel('chat.' . $chat->id);
                }
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
        return 'user.' . ($this->isOnline ? 'online' : 'offline');
    }
    
    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        $data = [
            'user_id' => $this->user['id'],
            'user_name' => $this->user['name'],
            'is_online' => $this->isOnline,
            'status' => $this->user['status'],
            'changed_at' => $this->changedAt,
        ];

        if (!$this->isOnline && $this->lastSeenAt) {
            $data['last_seen_at'] = $this->lastSeenAt;
        }

        return $data;
    }
    
    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function broadcastWhen()
    {
        // Only broadcast if we have a valid user
        return !empty($this->user['id']);
    }
}
