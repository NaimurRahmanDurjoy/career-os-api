<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobApplication extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'resume_id',
        'company_name',
        'role',
        'salary_range',
        'status',
        'job_description',
        'job_url',
        'applied_at',
    ];

    protected $casts = [
        'applied_at' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }

    public function notes()
    {
        return $this->hasMany(ApplicationNote::class);
    }
}
