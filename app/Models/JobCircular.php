<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class JobCircular extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'organization',
        'category',
        'location',
        'job_type',
        'experience_level',
        'salary_range',
        'application_deadline',
        'source_url',
        'circular_pdf_url',
        'description',
        'requirements',
        'skills_tags',
        'source_type',
        'external_id',
        'is_featured',
        'is_active',
        'views_count'
    ];

    protected $casts = [
        'application_deadline' => 'datetime',
        'skills_tags' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'views_count' => 'integer'
    ];

    protected $appends = ['is_expired', 'days_left'];

    public function getIsExpiredAttribute(): bool
    {
        if (!$this->application_deadline) {
            return false;
        }
        return Carbon::now()->greaterThan($this->application_deadline);
    }

    public function getDaysLeftAttribute(): ?int
    {
        if (!$this->application_deadline) {
            return null;
        }
        $now = Carbon::now();
        if ($now->greaterThan($this->application_deadline)) {
            return 0;
        }
        return (int) ceil($now->diffInDays($this->application_deadline, false));
    }
}
