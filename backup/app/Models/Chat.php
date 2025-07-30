<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\WhatsAppMessage;

class Chat extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chats';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'last_message_id',
        'last_message_at',
        'unread_count',
        'is_group',
        'is_archived',
        'is_muted',
        'metadata',
        'created_by',
    ];
    
    /**
     * The users that belong to the chat.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'chat_user', 'chat_id', 'user_id')
            ->withTimestamps()
            ->withPivot(['is_admin', 'muted_until']);
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'last_message_at' => 'datetime',
        'is_group' => 'boolean',
        'is_archived' => 'boolean',
        'is_muted' => 'boolean',
        'metadata' => 'array',
        'unread_count' => 'integer',
        'created_by' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'last_message',
        'avatar_url',
        'display_name',
        'is_online',
    ];

    /**
     * The default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'participants' => '[]',
        'metadata' => '{}',
        'is_group' => false,
        'is_archived' => false,
        'is_muted' => false,
        'unread_count' => 0,
    ];

    /**
     * The user who created the chat.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the unread messages for the chat.
     */
    public function unreadMessages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'chat', 'id')
            ->whereDoesntHave('readers', function($query) {
                $query->where('user_id', auth()->id());
            });
    }

    /**
     * Get the messages for the chat.
     */
    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'chat', 'id')
            ->orderBy('sending_time', 'desc');
    }

    /**
     * Get the last message for the chat.
     */
    public function lastMessage()
    {
        return $this->belongsTo(WhatsAppMessage::class, 'last_message_id');
    }

    /**
     * Scope a query to only include active chats.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    /**
     * Scope a query to only include group chats.
     */
    public function scopeGroups(Builder $query): Builder
    {
        return $query->where('is_group', true);
    }

    /**
     * Scope a query to only include direct (non-group) chats.
     */
    public function scopeDirect(Builder $query): Builder
    {
        return $query->where('is_group', false);
    }

    /**
     * Scope a query to only include chats with unread messages.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('unread_count', '>', 0);
    }

    /**
     * Scope a query to search for chats by name or participants.
     */
    public function scopeSearch(Builder $query, string $searchTerm): Builder
    {
        return $query->where('name', 'like', "%{$searchTerm}%")
            ->orWhereJsonContains('participants', $searchTerm);
    }

    /**
     * Get the last message attribute.
     */
    public function getLastMessageAttribute()
    {
        return $this->lastMessage;
    }

    /**
     * Get the avatar URL for the chat.
     */
    /**
     * Check if the chat is online.
     */
    public function getIsOnlineAttribute(): bool
    {
        if ($this->is_group) {
            return false;
        }

        $otherUser = $this->users()->where('users.id', '!=', auth()->id())->first();
        return $otherUser ? $otherUser->is_online : false;
    }

    /**
     * Get the other user in a direct chat.
     */
    public function getOtherUserAttribute()
    {
        if ($this->is_group) {
            return null;
        }
        return $this->users()->where('users.id', '!=', auth()->id())->first();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->is_group) {
            return $this->metadata['avatar_url'] ?? null;
        }

        // For direct chats, get the other participant's avatar
        $otherUser = $this->otherUser;
        return $otherUser ? $otherUser->avatar_url : null;
        $participant = $this->participants[0] ?? null;
        if ($participant && $user = User::where('phone', $participant)->first()) {
            return $user->avatar_url;
        }

        return null;
    }

    /**
     * Get the display name for the chat.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->is_group) {
            return $this->name;
        }

        // For direct chats, get the other participant's name
        $participant = $this->participants[0] ?? null;
        if ($participant && $user = User::where('phone', $participant)->first()) {
            return $user->name;
        }

        return $participant ?? 'Unknown';
    }

    /**
     * Increment the unread message count for the chat.
     */
    public function incrementUnreadCount(): void
    {
        $this->increment('unread_count');
    }

    /**
     * Reset the unread message count for the chat.
     */
    public function markAsRead(): void
    {
        $this->update(['unread_count' => 0]);
    }

    /**
     * Update the last message reference for the chat.
     */
    public function updateLastMessage(WhatsAppMessage $message): void
    {
        $this->update([
            'last_message_id' => $message->id,
            'last_message_at' => $message->sending_time,
        ]);
    }

    /**
     * Add a participant to the chat.
     */
    public function addParticipant(string $phoneNumber): void
    {
        $participants = $this->participants;
        if (!in_array($phoneNumber, $participants)) {
            $participants[] = $phoneNumber;
            $this->participants = $participants;
            $this->save();
        }
    }

    /**
     * Remove a participant from the chat.
     */
    public function removeParticipant(string $phoneNumber): void
    {
        $participants = $this->participants;
        $key = array_search($phoneNumber, $participants);
        
        if ($key !== false) {
            unset($participants[$key]);
            $this->participants = array_values($participants);
            $this->save();
        }
    }

    /**
     * Create a new direct chat between two participants.
     */
    public static function createDirectChat(string $user1, string $user2): self
    {
        $participants = array_unique([$user1, $user2]);
        sort($participants);
        
        $chat = self::firstOrCreate(
            [
                'is_group' => false,
                'participants' => $participants,
            ],
            [
                'name' => implode('_', $participants),
            ]
        );
        
        return $chat;
    }

    /**
     * Create a new group chat.
     */
    public static function createGroupChat(string $name, array $participants, array $metadata = []): self
    {
        $chat = self::create([
            'name' => $name,
            'is_group' => true,
            'participants' => $participants,
            'metadata' => $metadata,
        ]);
        
        return $chat;
    }
}
