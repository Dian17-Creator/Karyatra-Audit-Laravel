<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class TaccountPurgeLog extends Model
{
    protected $table = 'taccount_purge_log';

    protected $primaryKey = 'nid';

    /**
     * Gunakan custom timestamp.
     */
    public const CREATED_AT = 'dcreated';

    public const UPDATED_AT = 'dupdated';

    protected $fillable = [
        'ccompany_snapshot',
        'cowner_name_snapshot',
        'cowner_email_snapshot',
        'ccompany_folder_snapshot',
        'ddeletionrequested',
        'ddeleteafter',
        'dexecuted',
        'nid_executed_by',
        'cexecuted_by_snapshot',
        'cstatus',
        'cerror',
        'nsubscription_rows',
        'csummary',
    ];

    protected $casts = [
        'ddeletionrequested' => 'datetime',
        'ddeleteafter' => 'datetime',
        'dexecuted' => 'datetime',
        'nid_executed_by' => 'integer',
        'nsubscription_rows' => 'integer',
        'dcreated' => 'datetime',
        'dupdated' => 'datetime',
    ];

    /**
     * Relasi ke internal user yang menjalankan purge.
     */
    public function executedBy()
    {
        return $this->belongsTo(
            MinternalUser::class,
            'nid_executed_by',
            'nid'
        );
    }

    /**
     * Relasi ke lifecycle email logs.
     */
    public function lifecycleEmailLogs()
    {
        return $this->hasMany(
            TaccountLifecycleEmailLog::class,
            'nid_purge_log',
            'nid'
        );
    }
}
