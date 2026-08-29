<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use App\Models\Auth\Muser;

class TaccountDeleteWebRequest extends Model
{
    protected $table = 'taccount_delete_web_request';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'nid_owner',
        'ctokenhash',
        'dtokenexpires',
        'cstatus',
        'cmailstatus',
        'cmailerror',
        'dconfirmed',
    ];

    protected $casts = [
        'nid_owner' => 'integer',
        'dtokenexpires' => 'datetime',
        'dconfirmed' => 'datetime',
        'dcreated' => 'datetime',
    ];

    /**
     * Relasi ke user pemilik request penghapusan akun.
     */
    public function owner()
    {
        return $this->belongsTo(Muser::class, 'nid_owner', 'nid');
    }
}
