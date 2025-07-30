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

class ChatMuted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The chat instance.
     *
     * @var \App\Models\Chat
     */
    public $chat;

    /**
     * The user who muted/unmuted the chat.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * Whether the chat was muted or unmuted.
     *
     * @var bool
     */
    public $muted;

    /**
     * The timestamp when the mute will expire, or null if muted indefinitely.
     *
     * @var string|null
     */
    public $mutedUntil;

    /**
     * The timestamp when the action occurred.
     *
     * @var string
     */
    public $timestamp;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Chat  $chat
     * @param  \App\Models\User  $user
     * @param  bool  $muted
     * @param  \DateTimeInterface|null  $mutedUntil
     * @return void
     */
    public function __construct(Chat $chat, User $user, bool $muted = true, $mutedUntil = null)
    {
        $this->chat = $chat->only(['id', 'name', 'is_group']);
        $this->user = $user->only(['id', 'name', 'avatar_url']);
        $this->muted = $muted;
        $this->mutedUntil = $mutedUntil ? $mutedUntil->toIso8601String() : null;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Broadcast to the user's private channel
        return [
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
        return 'chat.' . ($this->muted ? 'muted' : 'unmuted');
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
            'muted' => $this->muted,
            'muted_until' => $this->mutedUntil,
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
        // Only broadcast if we have a valid chat and user
        return !empty($this->chat['id']) && !empty($this->user['id']);
    }
}
