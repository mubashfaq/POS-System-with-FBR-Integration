<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;
        protected $fillable = [
        'business_name',
        'industry',
        'ntn',
        'country',
        'city',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_business', 'business_id', 'user_id')
                    ->withTimestamps();
    }
}
