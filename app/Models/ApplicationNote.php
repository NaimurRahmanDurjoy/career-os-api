<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApplicationNote extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'job_application_id',
        'title',
        'content',
    ];

    public function jobApplication()
    {
        return $this->belongsTo(JobApplication::class);
    }
}
