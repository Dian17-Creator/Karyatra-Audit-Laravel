<?php

namespace App\Models\Audit;

use App\Models\Auth\Mdepartemen;
use App\Models\Auth\Muser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Maudit extends Model
{
    use HasFactory;

    protected $table = 'maudit';

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
        'ntotnilai',
        'nnilaimax',
        'npersen',
        'cauditee',
        'cphoto_path',
    ];

    protected $casts = [
        'started_at'  => 'datetime:Y-m-d H:i:s',
        'updated_at'  => 'datetime:Y-m-d H:i:s',
        'submitted_at' => 'datetime:Y-m-d H:i:s',
        'daudit'      => 'date:Y-m-d',

        'ntotnilai'   => 'decimal:2',
        'nnilaimax'   => 'decimal:2',
        'npersen'     => 'decimal:2',
    ];

    /**
     * Department yang diaudit
     */
    public function department()
    {
        return $this->belongsTo(Mdepartemen::class, 'nid_dept', 'nid');
    }

    /**
     * Auditor
     */
    public function auditor()
    {
        return $this->belongsTo(Muser::class, 'nid_auditor', 'nid');
    }

    /**
     * Responses dari Audit
     */
    public function responses()
    {
        return $this->hasMany(TauditHasil::class, 'nid_audit', 'nid');
    }
}
