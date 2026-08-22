<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobContact extends Model
{
    protected $fillable = [
        'job_id', 'name', 'role', 'email', 'linkedin_url', 'last_contact_date', 'notes'
    ];
    
    protected $casts = [
        'last_contact_date' => 'date',
    ];

    public function jobApplication()
    {
        return $this->belongsTo(JobApplication::class, 'job_id');
    }
}
