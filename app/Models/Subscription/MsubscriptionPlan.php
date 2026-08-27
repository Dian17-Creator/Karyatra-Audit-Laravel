<?php

namespace App\Models\Subscription;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsubscriptionPlan extends Model
{
    use HasFactory;

    protected $table = 'msubscription_plan';
    protected $primaryKey = 'nid';
    public $timestamps = true;

    const CREATED_AT = 'dcreated';
    const UPDATED_AT = 'dupdated';

    protected $fillable = [
        'ccode',
        'cnama',
        'nduration_months',
        'nprice',
        'nreference_price',
        'cdescription',
        'cbadge',
        'fenabled',
        'nsort',
    ];

    protected $casts = [
        'nid'              => 'integer',
        'nduration_months' => 'integer',
        'nprice'           => 'float',
        'nreference_price' => 'float',
        'fenabled'         => 'boolean',
        'nsort'            => 'integer',
        'dcreated'         => 'datetime',
        'dupdated'         => 'datetime',
    ];

    /**
     * Relationship: One plan has many subscriptions
     */
    public function subscriptions()
    {
        return $this->hasMany(Tsubscription::class, 'nid_plan', 'nid');
    }

    /**
     * Scope: Active and purchasable plans
     */
    public function scopeEnabled($query)
    {
        return $query->where('fenabled', 1)
                     ->whereNotNull('nprice')
                     ->where('nprice', '>', 0);
    }
}
