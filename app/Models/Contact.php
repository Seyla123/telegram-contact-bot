<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'id',
        'first_name',
        'last_name',
        'username',
        'phone_number'
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
