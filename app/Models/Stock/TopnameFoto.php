<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use App\Models\Stock\TopnameHasil;

class TopnameFoto extends Model
{
    protected $table = 'topname_foto';

    protected $primaryKey = 'nid';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nid_hasil',
        'nurut',
        'cket',
        'cphoto_path',
        'uploaded_at',
    ];

    protected $casts = [
        'nid' => 'integer',
        'nid_hasil' => 'integer',
        'nurut' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    /**
     * Accessor & Mutator for nid_resp (legacy compatibility)
     */
    public function getNidRespAttribute()
    {
        return $this->nid_hasil ?? ($this->attributes['nid_hasil'] ?? null);
    }

    public function setNidRespAttribute($value)
    {
        $this->attributes['nid_hasil'] = $value;
    }

    /**
     * Accessor & Mutator for nsequence (legacy compatibility)
     */
    public function getNsequenceAttribute()
    {
        return $this->nurut ?? ($this->attributes['nurut'] ?? null);
    }

    public function setNsequenceAttribute($value)
    {
        $this->attributes['nurut'] = $value;
    }

    /**
     * Accessor for caction (legacy compatibility)
     */
    public function getCactionAttribute()
    {
        return null;
    }

    /**
     * Relasi ke hasil opname.
     */
    public function response()
    {
        return $this->belongsTo(TopnameHasil::class, 'nid_hasil', 'nid');
    }
}
