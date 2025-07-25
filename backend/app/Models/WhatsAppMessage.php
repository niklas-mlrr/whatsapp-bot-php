<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'messages';

    protected $fillable = [
        'sender',
        'chat',
        'type',
        'content',
        'media',
        'mimetype',
        'sending_time',
    ];

    protected $casts = [
        'sending_time' => 'datetime',
    ];
} 