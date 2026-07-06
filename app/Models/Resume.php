<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Resume extends Model
{
    use HasUuids; // UUID primary key support

    protected $fillable = [
        'user_id', 'file_name', 'file_path', 'version_name', 
        'parsed_content', 'ats_score', 'ai_suggestions', 'is_primary', 'status'
    ];

    // Cast attributes to appropriate data types
    protected $casts = [
        'parsed_content' => 'array',
        'ai_suggestions' => 'array',
        'is_primary' => 'boolean',
    ];

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
