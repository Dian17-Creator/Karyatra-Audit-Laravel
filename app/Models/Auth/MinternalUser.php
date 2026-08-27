<?php

namespace App\Models\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MinternalUser extends Authenticatable
{
    use HasFactory;

    protected $table = 'minternal_user';
    protected $primaryKey = 'nid';
    public $timestamps = true;

    const CREATED_AT = 'dcreated';
    const UPDATED_AT = 'dupdated';

    protected $fillable = [
        'cnama',
        'cemail',
        'cpassword',
        'crole',
        'factive',
    ];

    protected $hidden = [
        'cpassword',
    ];

    protected $casts = [
        'nid'      => 'integer',
        'factive'  => 'boolean',
        'dcreated' => 'datetime',
        'dupdated' => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->cpassword;
    }

    public function isActive(): bool
    {
        return (bool) $this->factive;
    }

    public function isFinance(): bool
    {
        return strtolower(trim((string) $this->crole)) === 'finance';
    }

    public function isSuperAdmin(): bool
    {
        return strtolower(trim((string) $this->crole)) === 'superadmin';
    }
}
