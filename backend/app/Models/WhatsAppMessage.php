<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Chat;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'messages';

    protected $fillable = [
        'sender',
        'sender_id',
        'chat',
        'chat_id',
        'type',
        'direction',
        'status',
        'content',
        'media',
        'mimetype',
        'sending_time',
        'read_at',
        'reactions',
        'metadata',
    ];

    protected $casts = [
        'sending_time' => 'datetime',
        'read_at' => 'datetime',
        'reactions' => 'array',
        'metadata' => 'array',
        'is_read' => 'boolean',
    ];
    
    protected $appends = [
        'is_read', 
        'media_url', 
        'thumbnail_url',
        'sender_name',
        'sender_avatar',
    ];

    /**
     * The users who have read this message.
     */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_reads')
            ->withTimestamps()
            ->withPivot('read_at');
    }

    /**
     * The user who sent this message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the sender's name.
     */
    public function getSenderNameAttribute(): ?string
    {
        if ($this->sender) {
            return $this->sender->name;
        }
        return $this->sender; // Fallback to the sender field if no user relationship
    }

    /**
     * Get the sender's avatar URL.
     */
    public function getSenderAvatarAttribute(): ?string
    {
        if ($this->sender) {
            return $this->sender->avatar_url;
        }
        return null;
    }
    
    // Default values for attributes
    protected $attributes = [
        'reactions' => '{}',
        'metadata' => '{}',
        'status' => 'pending',
    ];

    // Scopes
    public function scopeIncoming(Builder $query): Builder
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeOutgoing(Builder $query): Builder
    {
        return $query->where('direction', 'outgoing');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
    
    public function scopeForChat(Builder $query, string $chatId): Builder
    {
        return $query->where('chat', $chatId);
    }
    
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('sending_time', '>=', now()->subDays($days))
                    ->orderBy('sending_time', 'desc');
    }
    
    public function scopeWithMedia(Builder $query): Builder
    {
        return $query->whereNotNull('media');
    }
    
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
    
    public function scopeSearch(Builder $query, string $searchTerm): Builder
    {
        return $query->where(function($q) use ($searchTerm) {
            $q->where('content', 'like', "%{$searchTerm}%")
              ->orWhere('sender', 'like', "%{$searchTerm}%")
              ->orWhere('chat', 'like', "%{$searchTerm}%");
        });
    }

    // Relationships
    public function senderUser()
    {
        return $this->belongsTo(User::class, 'sender', 'phone');
    }
    
    /**
     * Get the chat this message belongs to.
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }

    // Accessors & Mutators
    public function getIsReadAttribute(): bool
    {
        return !is_null($this->read_at);
    }
    
    public function getMediaUrlAttribute(): ?string
    {
        if (!$this->media) {
            return null;
        }
        
        if (filter_var($this->media, FILTER_VALIDATE_URL)) {
            return $this->media;
        }
        
        if (Storage::disk('public')->exists($this->media)) {
            return Storage::disk('public')->url($this->media);
        }
        
        if (config('filesystems.default') === 's3' && Storage::disk('s3')->exists($this->media)) {
            return Storage::disk('s3')->url($this->media);
        }
        
        return null;
    }
    
    public function getThumbnailUrlAttribute(): ?string
    {
        $thumbnailPath = $this->metadata['thumbnail_path'] ?? null;
        
        if (!$thumbnailPath) {
            return null;
        }
        
        if (filter_var($thumbnailPath, FILTER_VALIDATE_URL)) {
            return $thumbnailPath;
        }
        
        if (Storage::disk('public')->exists($thumbnailPath)) {
            return Storage::disk('public')->url($thumbnailPath);
        }
        
        if (config('filesystems.default') === 's3' && Storage::disk('s3')->exists($thumbnailPath)) {
            return Storage::disk('s3')->url($thumbnailPath);
        }
        
        return null;
    }
    
    public function getMediaMetadataAttribute(): array
    {
        return $this->metadata['media_metadata'] ?? [];
    }
    
    // Helper Methods
    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return true;
        }
        
        return $this->update([
            'read_at' => now(),
            'status' => 'read',
        ]);
    }
    
    public function addReaction(string $userId, string $reaction): bool
    {
        $reactions = $this->reactions ?? [];
        $reactions[$userId] = $reaction;
        
        return $this->update(['reactions' => $reactions]);
    }
    
    public function removeReaction(string $userId): bool
    {
        $reactions = $this->reactions ?? [];
        
        if (!isset($reactions[$userId])) {
            return true;
        }
        
        unset($reactions[$userId]);
        
        return $this->update([
            'reactions' => !empty($reactions) ? $reactions : null,
        ]);
    }
    
    public function updateStatus(string $status, ?string $error = null): bool
    {
        $updateData = ['status' => $status];
        
        if ($status === 'read' && !$this->read_at) {
            $updateData['read_at'] = now();
        }
        
        if ($status === 'failed' && $error) {
            $metadata = $this->metadata ?? [];
            $metadata['error'] = $error;
            $updateData['metadata'] = $metadata;
        }
        
        return $this->update($updateData);
    }
    
    public function getMediaType(): string
    {
        if (!$this->mimetype) {
            return 'unknown';
        }
        
        [$type] = explode('/', $this->mimetype);
        
        return in_array($type, ['image', 'video', 'audio']) ? $type : 'document';
    }
}