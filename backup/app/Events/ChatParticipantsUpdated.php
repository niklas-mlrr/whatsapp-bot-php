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

class ChatParticipantsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The chat instance.
     *
     * @var \App\Models\Chat
     */
    public $chat;

    /**
     * The user who performed the update.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The type of update (added, removed, left, etc.).
     *
     * @var string
     */
    public $updateType;

    /**
     * The participants that were affected by the update.
     *
     * @var array
     */
    public $participants;

    /**
     * The timestamp when the update occurred.
     *
     * @var string
     */
    public $updatedAt;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Chat  $chat
     * @param  \App\Models\User  $user
     * @param  string  $updateType
     * @param  array  $participants
     * @return void
     */
    public function __construct(Chat $chat, User $user, string $updateType, array $participants = [])
    {
        $this->chat = $chat->only(['id', 'name', 'is_group', 'participants']);
        $this->user = $user->only(['id', 'name', 'avatar_url']);
        $this->updateType = $updateType;
        $this->participants = $participants;
        $this->updatedAt = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Broadcast to all participants of the chat
        $channels = [
            new PrivateChannel('chat.' . $this->chat['id']),
        ];

        // Also notify each affected participant directly
        foreach ($this->participants as $participant) {
            $channels[] = new PrivateChannel('user.' . $participant['id']);
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
        return 'chat.participants.updated';
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
            'update_type' => $this->updateType,
            'participants' => $this->participants,
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
        // Only broadcast if we have a valid chat, user, and update type
        return !empty($this->chat['id']) && !empty($this->user['id']) && !empty($this->updateType);
    }
}
