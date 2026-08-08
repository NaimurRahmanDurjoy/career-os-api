<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'identifier',
        'name',
        'price_bdt',
        'price_usd',
        'features',
        'limits',
        'is_popular',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'limits' => 'array',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
    ];
}
