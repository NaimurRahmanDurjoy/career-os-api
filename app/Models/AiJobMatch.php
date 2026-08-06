<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiJobMatch extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'job_application_id', 'match_score', 'verdict',
        'generated_cover_letter', 'interview_prep_questions',
    ];

    protected $casts = [
        'interview_prep_questions' => 'array',
    ];

    public function jobApplication()
    {
        return $this->belongsTo(JobApplication::class);
    }
}
