<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class TaccountLifecycleEmailLog extends Model
{
    protected $table = 'taccount_lifecycle_email_log';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'nid_owner_snapshot',
        'nid_purge_log',
        'cevent',
        'ccompany_snapshot',
        'cowner_name_snapshot',
        'cowner_email_snapshot',
        'cstatus',
        'cerror',
        'dsent',
    ];

    protected $casts = [
        'nid_owner_snapshot' => 'integer',
        'nid_purge_log' => 'integer',
        'dsent' => 'datetime',
        'dcreated' => 'datetime',
    ];
}
