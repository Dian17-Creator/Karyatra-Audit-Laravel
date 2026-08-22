<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Auth\Mdepartemen;
use App\Models\Auth\Muser;
use App\Models\Stock\TopnameHasil;

class Mopname extends Model
{
    use HasFactory;

    protected $table = 'mopname';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'cdocid',
        'nid_dept',
        'nid_auditor',
        'cstatus',
        'started_at',
        'updated_at',
        'submitted_at',
        'daudit',
        'cauditee',
        'cphoto_path',
    ];

    protected $casts = [
        'nid' => 'integer',
        'nid_dept' => 'integer',
        'nid_auditor' => 'integer',

        'started_at' => 'datetime',
        'updated_at' => 'datetime',
        'submitted_at' => 'datetime',

        'daudit' => 'date',
    ];

    /**
     * Departemen yang diaudit
     */
    public function department()
    {
        return $this->belongsTo(
            Mdepartemen::class,
            'nid_dept',
            'nid'
        );
    }

    /**
     * Auditor yang melakukan audit
     */
    public function auditor()
    {
        return $this->belongsTo(
            Muser::class,
            'nid_auditor',
            'nid'
        );
    }

    /**
     * Hasil / rincian opname
     */
    public function responses()
    {
        return $this->hasMany(
            TopnameHasil::class,
            'nid_opname',
            'nid'
        );
    }
}
