<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Stock\MauditItemgrp;
use App\Models\Stock\MauditInvresp;

class MauditItem extends Model
{
    use HasFactory;

    protected $table = 'maudit_items';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'nid_grp',
        'citemname',
        'nsequence',
    ];

    protected $casts = [
        'nid_grp'    => 'integer',
        'nsequence'  => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Relasi ke grup audit.
     */
    public function group()
    {
        return $this->belongsTo(MauditItemgrp::class, 'nid_grp', 'nid');
    }

    /**
     * Relasi ke respons audit/opname.
     */
    public function invresps()
    {
        return $this->hasMany(MauditInvresp::class, 'nid_item', 'nid');
    }
}
