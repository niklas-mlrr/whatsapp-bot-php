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

class UserProfileUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The user instance.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The fields that were updated.
     *
     * @var array
     */
    public $updatedFields;

    /**
     * The timestamp when the profile was updated.
     *
     * @var string
     */
    public $updatedAt;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\User  $user
     * @param  array  $updatedFields
     * @return void
     */
    public function __construct(User $user, array $updatedFields = [])
    {
        $this->user = $user->only(['id', 'name', 'email', 'avatar_url', 'status', 'last_seen_at']);
        $this->updatedFields = $updatedFields;
        $this->updatedAt = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Broadcast to the user's private channel and all their chats
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
        return 'user.profile.updated';
    }
    
    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'user_id' => $this->user['id'],
            'user' => $this->user,
            'updated_fields' => $this->updatedFields,
            'updated_at' => $this->updatedAt,
        ];
    }
    
    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function broadcastWhen()
    {
        // Only broadcast if we have a valid user and at least one updated field
        return !empty($this->user['id']) && !empty($this->updatedFields);
    }
}
