<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Stock\Mbarang;
use App\Models\Stock\Mopname;
use App\Models\Stock\TopnameFoto;

class TopnameHasil extends Model
{
    use HasFactory;

    protected $table = 'topname_hasil';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'nid_opname',
        'nid_barang',
        'nqty_stock',
        'nqty_real',
        'nselisih',
        'nsel_lebih',
        'nsel_kurang',
        'fna',
        'cket',
        'updated_at',
    ];

    protected $casts = [
        'nid'          => 'integer',
        'nid_opname'   => 'integer',
        'nid_barang'   => 'integer',
        'nqty_stock'   => 'decimal:2',
        'nqty_real'    => 'decimal:2',
        'nselisih'     => 'decimal:2',
        'nsel_lebih'   => 'decimal:2',
        'nsel_kurang'  => 'decimal:2',
        'fna'          => 'boolean',
        'updated_at'   => 'datetime',
    ];

    /**
     * Accessors & Mutators for legacy field mapping
     */
    public function getNidAuditAttribute()
    {
        return $this->nid_opname ?? ($this->attributes['nid_opname'] ?? null);
    }

    public function setNidAuditAttribute($value)
    {
        $this->attributes['nid_opname'] = $value;
    }

    public function getNidItemAttribute()
    {
        return $this->nid_barang ?? ($this->attributes['nid_barang'] ?? null);
    }

    public function setNidItemAttribute($value)
    {
        $this->attributes['nid_barang'] = $value;
    }

    public function getNdiffAttribute()
    {
        return $this->nselisih ?? ($this->attributes['nselisih'] ?? 0);
    }

    public function setNdiffAttribute($value)
    {
        $this->attributes['nselisih'] = $value;
    }

    public function getNdiffUnderAttribute()
    {
        return $this->nsel_kurang ?? ($this->attributes['nsel_kurang'] ?? 0);
    }

    public function setNdiffUnderAttribute($value)
    {
        $this->attributes['nsel_kurang'] = $value;
    }

    public function getNdiffOverAttribute()
    {
        return $this->nsel_lebih ?? ($this->attributes['nsel_lebih'] ?? 0);
    }

    public function setNdiffOverAttribute($value)
    {
        $this->attributes['nsel_lebih'] = $value;
    }

    /**
     * Relasi ke header opname.
     */
    public function opname()
    {
        return $this->belongsTo(Mopname::class, 'nid_opname', 'nid');
    }

    /**
     * Relasi ke barang.
     */
    public function item()
    {
        return $this->belongsTo(Mbarang::class, 'nid_barang', 'nid');
    }

    public function barang()
    {
        return $this->belongsTo(Mbarang::class, 'nid_barang', 'nid');
    }

    /**
     * Relasi ke foto.
     */
    public function photos()
    {
        return $this->hasMany(TopnameFoto::class, 'nid_hasil', 'nid');
    }
}
