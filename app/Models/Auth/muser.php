<?php

namespace App\Models\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Muser extends Authenticatable
{
    use HasFactory;

    protected $table = 'muser';
    protected $primaryKey = 'nid';
    public $timestamps = false;

    protected $fillable = [
        'cemail',
        'cnamalengkap',
        'cperusahaan',
        'cpassword',
        'dcreated',
        'fowner',
        'dnonactive',
        'clevel',
        'demailverified',
        'cverifytokenhash',
        'dverifyexpires',
        'ntrialauditcreated',
        'ntrialopnamecreated',
    ];

    protected $hidden = [
        'cpassword',
        'cverifytokenhash',
    ];

    protected $casts = [
        'nid'                 => 'integer',
        'dcreated'            => 'datetime',
        'fowner'              => 'boolean',
        'dnonactive'          => 'date',
        'demailverified'      => 'datetime',
        'dverifyexpires'      => 'datetime',
        'ntrialauditcreated'  => 'integer',
        'ntrialopnamecreated' => 'integer',
    ];

    public function getAuthPassword()
    {
        return $this->cpassword;
    }

    /**
     * Backward compatibility accessors
     */
    public function getCfullnameAttribute()
    {
        return $this->cnamalengkap;
    }

    public function getCcompanyAttribute()
    {
        return $this->cperusahaan;
    }

    public function getFactiveAttribute()
    {
        return $this->isActive();
    }

    public function getFauditAttribute()
    {
        return $this->isAudit();
    }

    public function getFadminAttribute()
    {
        return $this->isAdmin();
    }

    public function getFsuperAttribute()
    {
        return $this->isOwner();
    }

    /**
     * Helper methods
     */
    public function isActive(): bool
    {
        if ($this->dnonactive === null) {
            return true;
        }
        return Carbon::parse($this->dnonactive)->isFuture();
    }

    public function isOwner(): bool
    {
        return (bool) $this->fowner;
    }

    public function isAdmin(): bool
    {
        return $this->clevel === 'admin' || (bool) $this->fowner;
    }

    public function isAudit(): bool
    {
        return in_array($this->clevel, ['admin', 'audit']);
    }
}

