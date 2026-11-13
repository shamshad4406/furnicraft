<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LoginHistory extends Model
{
    protected $fillable = [
        'guard',
        'authenticatable_type',
        'authenticatable_id',
        'email',
        'ip_address',
        'user_agent',
        'successful',
        'reason',
        'logged_in_at',
        'logged_out_at',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'logged_in_at' => 'datetime',
            'logged_out_at' => 'datetime',
        ];
    }

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
