<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    protected $fillable = ['user_id', 'feature_name', 'tokens_used'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
