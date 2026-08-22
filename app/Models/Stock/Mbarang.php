<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Stock\MkatBarang;
use App\Models\Stock\TopnameHasil;

class Mbarang extends Model
{
    use HasFactory;

    protected $table = 'mbarang';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'nid_kat',
        'cbarang',
        'nurut',
    ];

    protected $casts = [
        'nid' => 'integer',
        'nid_kat' => 'integer',
        'nurut' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Accessor & Mutator untuk citemname (legacy compatibility)
     */
    public function getCitemnameAttribute()
    {
        return $this->cbarang ?? ($this->attributes['cbarang'] ?? null);
    }

    public function setCitemnameAttribute($value)
    {
        $this->attributes['cbarang'] = $value;
    }

    /**
     * Accessor & Mutator untuk nsequence (legacy compatibility)
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
     * Accessor & Mutator untuk nid_grp (legacy compatibility)
     */
    public function getNidGrpAttribute()
    {
        return $this->nid_kat ?? ($this->attributes['nid_kat'] ?? null);
    }

    public function setNidGrpAttribute($value)
    {
        $this->attributes['nid_kat'] = $value;
    }

    /**
     * Relasi ke kategori barang.
     */
    public function group()
    {
        return $this->belongsTo(MkatBarang::class, 'nid_kat', 'nid');
    }

    public function category()
    {
        return $this->belongsTo(MkatBarang::class, 'nid_kat', 'nid');
    }

    /**
     * Relasi ke respons audit/opname.
     */
    public function invresps()
    {
        return $this->hasMany(TopnameHasil::class, 'nid_barang', 'nid');
    }

    public function results()
    {
        return $this->hasMany(TopnameHasil::class, 'nid_barang', 'nid');
    }
}
