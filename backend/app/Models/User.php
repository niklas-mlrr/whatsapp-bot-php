<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'status',
        'last_seen_at',
        'settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'password' => 'hashed',
        'settings' => 'array',
    ];

    protected $appends = [
        'avatar_url',
        'is_online',
    ];

    // Relationships
    public function sentMessages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'sender', 'phone');
    }

    public function chats()
    {
        return $this->belongsToMany(Chat::class, 'chat_user', 'user_id', 'chat_id')
            ->withTimestamps()
            ->withPivot(['is_admin', 'muted_until', 'read_at']);
    }

    // Accessors
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
                return $this->avatar;
            }
            
            if (Storage::disk('public')->exists($this->avatar)) {
                return Storage::disk('public')->url($this->avatar);
            }
        }
        
        // Default avatar based on name
        $name = urlencode(trim($this->name));
        return "https://ui-avatars.com/api/?name={$name}&background=random&color=fff&size=128";
    }

    public function getIsOnlineAttribute(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }
        
        return $this->last_seen_at->diffInMinutes(now()) < 5;
    }

    // Helper Methods
    public function updateLastSeen(): void
    {
        $this->last_seen_at = now();
        $this->save();
    }

    public function unreadMessagesCount(): int
    {
        return $this->chats()->sum('unread_count');
    }

    public function getUnreadChats(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->chats()
            ->where('unread_count', '>', 0)
            ->orderBy('last_message_at', 'desc')
            ->get();
    }

    public function updateNotificationSettings(array $settings): void
    {
        $currentSettings = $this->settings ?? [];
        $this->settings = array_merge($currentSettings, $settings);
        $this->save();
    }

    public function hasUnreadMessagesInChat(string $chatId): bool
    {
        $chat = $this->chats()->find($chatId);
        return $chat ? $chat->pivot->read_at < $chat->last_message_at : false;
    }
}
