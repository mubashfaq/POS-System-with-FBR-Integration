<?php

namespace App\Helpers;

use App\Models\Business;
use App\Models\UserBusiness;
use Illuminate\Support\Facades\Auth;

class UserBusinessWrapper
{
    public $userBusiness;
    public $user;
    public $isSuperAdmin = false;
    public $business = null;

    public function __construct($userBusiness = null, $user = null, $isSuperAdmin = false, $business = null)
    {
        $this->userBusiness = $userBusiness;
        $this->user = $user;
        $this->isSuperAdmin = $isSuperAdmin;
        $this->business = $business ?? $userBusiness?->business ?? null;
    }

    public function hasPermissionTo($permission)
    {
        if ($this->isSuperAdmin) {
            return true;
        }

        if (!$this->userBusiness) {
            return false;
        }

        return $this->userBusiness->roles
            ->flatMap->permissions
            ->pluck('name')
            ->contains($permission);
    }

    public function hasAnyPermission(...$permissions)
    {
        if ($this->isSuperAdmin) {
            return true;
        }

        if (!$this->userBusiness) {
            return false;
        }

        $userPermissions = $this->userBusiness->roles
            ->flatMap->permissions
            ->pluck('name');

        foreach ($permissions as $permission) {
            if (is_array($permission)) {
                foreach ($permission as $p) {
                    if ($userPermissions->contains($p)) {
                        return true;
                    }
                }
            } else {
                if ($userPermissions->contains($permission)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hasAllPermissions(...$permissions)
    {
        if ($this->isSuperAdmin) {
            return true;
        }

        if (!$this->userBusiness) {
            return false;
        }

        $userPermissions = $this->userBusiness->roles
            ->flatMap->permissions
            ->pluck('name');

        return collect($permissions)
            ->flatten()
            ->every(fn($p) => $userPermissions->contains($p));
    }

    public function hasPermission($permission)
    {
        return $this->hasPermissionTo($permission);
    }

    public function getBusinessId()
    {
        return $this->business->id
            ?? $this->userBusiness?->business_id
            ?? session('business_id');
    }
}
