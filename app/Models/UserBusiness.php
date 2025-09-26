<?php

namespace App\Models;

use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBusiness extends Model
{
    use HasFactory, HasRoles;
    protected $table = 'user_business';

    protected $fillable = [
        'user_id',
        'business_id',
    ];

    protected $guard_name = 'web';


      public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ✅ Relationship with Business
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    // ✅ Custom method to check permissions safely
    public function hasPermission($permission)
    {
        return $this->hasPermissionTo($permission);
    }

    public function hasAnyPermission($permissions)
    {
        if (!is_array($permissions)) {
            $permissions = [$permissions];
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

}
