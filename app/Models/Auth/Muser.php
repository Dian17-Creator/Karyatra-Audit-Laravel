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
        'niddept',
        'demailverified',
        'cverifytokenhash',
        'dverifyexpires',
        'ntrialauditcreated',
        'ntrialopnamecreated',
        'dcompanynonactive',
        'ddeletionrequested',
        'ddeleteafter',
        'fdeletionwasinactive',
        'ceulaversion',
        'deulaaccepted',
    ];

    protected $hidden = [
        'cpassword',
        'cverifytokenhash',
    ];

    protected $casts = [
        'nid'                  => 'integer',
        'niddept'              => 'integer',
        'dcreated'             => 'datetime',
        'fowner'               => 'boolean',
        'dnonactive'           => 'date',
        'demailverified'       => 'datetime',
        'dverifyexpires'       => 'datetime',
        'ntrialauditcreated'   => 'integer',
        'ntrialopnamecreated'  => 'integer',
        'dcompanynonactive'    => 'datetime',
        'ddeletionrequested'   => 'datetime',
        'ddeleteafter'         => 'datetime',
        'fdeletionwasinactive' => 'boolean',
        'deulaaccepted'        => 'datetime',
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

    public function isActive(): bool
    {
        return $this->dnonactive === null;
    }

    public function isOwner(): bool
    {
        return (bool) $this->fowner;
    }

    public function isAdmin(): bool
    {
        return (bool) $this->fowner || strtolower(trim((string) $this->clevel)) === 'admin';
    }

    public function isAuditor(): bool
    {
        return !$this->isOwner() && strtolower(trim((string) $this->clevel)) === 'audit';
    }

    public function isAudit(): bool
    {
        return (bool) $this->fowner || in_array(strtolower(trim((string) $this->clevel)), ['admin', 'audit']);
    }

    public function issueVerificationToken(): string
    {
        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->cverifytokenhash = hash('sha256', $rawToken);
        $this->dverifyexpires = Carbon::now()->addHours(48);
        $this->save();

        return $rawToken;
    }

    public function isEmailVerified(): bool
    {
        return $this->demailverified !== null;
    }

    public function isTrial(): bool
    {
        return !$this->isEmailVerified();
    }

    public function getCompanyOwner(): ?Muser
    {
        if (empty($this->cperusahaan)) {
            return null;
        }

        return static::where('cperusahaan', $this->cperusahaan)
            ->where('fowner', 1)
            ->first();
    }

    public function isCompanyInactive(): bool
    {
        $owner = $this->getCompanyOwner();
        return $owner ? $owner->dcompanynonactive !== null : false;
    }

    public function isDeletionPending(): bool
    {
        $owner = $this->getCompanyOwner();
        return $owner ? $owner->ddeletionrequested !== null : false;
    }

    public function getCompanyLifecycleState(): array
    {
        $owner = $this->getCompanyOwner();

        return [
            'is_owner'             => $this->isOwner(),
            'is_company_inactive'  => $owner ? $owner->dcompanynonactive !== null : false,
            'is_deletion_pending'  => $owner ? $owner->ddeletionrequested !== null : false,
            'dcompanynonactive'    => $owner?->dcompanynonactive?->toIso8601String(),
            'ddeletionrequested'   => $owner?->ddeletionrequested?->toIso8601String(),
            'ddeleteafter'         => $owner?->ddeleteafter?->toIso8601String(),
            'fdeletionwasinactive' => $owner ? (bool) $owner->fdeletionwasinactive : false,
        ];
    }
}
