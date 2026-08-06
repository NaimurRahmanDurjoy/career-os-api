<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreparationTracker extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'exam_type',
        'syllabus_roadmap',
        'overall_progress',
    ];

    protected $casts = [
        'syllabus_roadmap' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
