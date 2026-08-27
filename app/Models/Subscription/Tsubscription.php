<?php

namespace App\Models\Subscription;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tsubscription extends Model
{
    use HasFactory;

    protected $table = 'tsubscription';
    protected $primaryKey = 'nid';
    public $timestamps = true;

    const CREATED_AT = 'dcreated';
    const UPDATED_AT = 'dupdated';

    protected $fillable = [
        'nid_owner',
        'nid_plan',
        'cplan_name',
        'nduration_months',
        'namount',
        'cstatus',
        'cpayment_ref',
        'cpayment_proof',
        'dstart',
        'dend',
        'nid_reviewed_by',
        'dreviewed',
        'cnote',
        'cdecision_email_status',
        'ddecision_email_sent',
    ];

    protected $casts = [
        'nid'                   => 'integer',
        'nid_owner'             => 'integer',
        'nid_plan'              => 'integer',
        'nduration_months'      => 'integer',
        'namount'               => 'float',
        'dstart'                => 'datetime',
        'dend'                  => 'datetime',
        'nid_reviewed_by'       => 'integer',
        'dreviewed'             => 'datetime',
        'ddecision_email_sent'  => 'datetime',
        'dcreated'              => 'datetime',
        'dupdated'              => 'datetime',
    ];

    /**
     * Relationship: Owner user
     */
    public function owner()
    {
        return $this->belongsTo(\App\Models\Auth\Muser::class, 'nid_owner', 'nid');
    }

    /**
     * Relationship: Subscription plan
     */
    public function plan()
    {
        return $this->belongsTo(MsubscriptionPlan::class, 'nid_plan', 'nid');
    }

    /**
     * Relationship: Internal backoffice reviewer
     */
    public function reviewer()
    {
        return $this->belongsTo(\App\Models\Auth\MinternalUser::class, 'nid_reviewed_by', 'nid');
    }
}
