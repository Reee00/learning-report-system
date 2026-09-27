<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'user_role', 'action', 'subject_type', 'subject_id',
        'description', 'metadata', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
        'subject_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
