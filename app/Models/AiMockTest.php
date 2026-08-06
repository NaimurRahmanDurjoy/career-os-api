<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiMockTest extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'topic_name',
        'quiz_data',
        'user_answers',
        'score',
        'ai_explanations',
    ];

    protected $casts = [
        'quiz_data' => 'array',
        'user_answers' => 'array',
        'ai_explanations' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
