<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'contact_id',
        'direction',
        'message_type',
        'message',
        'file_id',
        'file_path',
        'file_size',
        'file_name',
        'sent_at',
        'status',
        'thread_id',
        'is_admin',
        'duration',
        'mime_type',
        'width',
        'height',
        'caption'  
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function thread()
    {
        return $this->belongsTo(Message::class, 'thread_id');
    }

    public function replies()
    {
        return $this->hasMany(Message::class, 'thread_id');
    }
}
