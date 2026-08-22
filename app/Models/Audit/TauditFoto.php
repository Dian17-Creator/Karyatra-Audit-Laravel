<?php

namespace App\Models\Audit;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TauditFoto extends Model
{
    use HasFactory;

    protected $table = 'taudit_foto';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'nid_hasil',
        'nid_resp',
        'nurut',
        'nsequence',
        'cket',
        'ctindakan',
        'caction',
        'cphoto_path',
        'uploaded_at',
    ];

    protected $casts = [
        'nid_hasil'   => 'integer',
        'nid_resp'    => 'integer',
        'nurut'       => 'integer',
        'uploaded_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function getNidRespAttribute()
    {
        return $this->nid_hasil ?? ($this->attributes['nid_resp'] ?? null);
    }

    public function setNidRespAttribute($value)
    {
        $this->attributes['nid_hasil'] = $value;
        $this->attributes['nid_resp'] = $value;
    }

    public function getCactionAttribute()
    {
        return $this->ctindakan ?? ($this->attributes['caction'] ?? null);
    }

    public function setCactionAttribute($value)
    {
        $this->attributes['ctindakan'] = $value;
    }

    public function getNsequenceAttribute()
    {
        return $this->nurut ?? ($this->attributes['nsequence'] ?? null);
    }

    public function setNsequenceAttribute($value)
    {
        $this->attributes['nurut'] = $value;
    }

    /**
     * Response audit yang memiliki foto ini.
     */
    public function response()
    {
        return $this->belongsTo(TauditHasil::class, 'nid_hasil', 'nid');
    }
}

