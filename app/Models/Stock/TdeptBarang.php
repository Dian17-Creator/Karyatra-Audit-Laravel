<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Auth\Mdepartemen;
use App\Models\Stock\Mbarang;

class TdeptBarang extends Model
{
    use HasFactory;

    protected $table = 'tdept_barang';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = [
        'nid_dept',
        'nid_barang',
    ];

    protected $casts = [
        'nid_dept' => 'integer',
        'nid_barang' => 'integer',
    ];

    /**
     * Accessor & Mutator untuk nid_item (legacy compatibility)
     */
    public function getNidItemAttribute()
    {
        return $this->nid_barang ?? ($this->attributes['nid_barang'] ?? null);
    }

    public function setNidItemAttribute($value)
    {
        $this->attributes['nid_barang'] = $value;
    }

    public function department()
    {
        return $this->belongsTo(
            Mdepartemen::class,
            'nid_dept',
            'nid'
        );
    }

    public function item()
    {
        return $this->belongsTo(
            Mbarang::class,
            'nid_barang',
            'nid'
        );
    }

    public function barang()
    {
        return $this->belongsTo(
            Mbarang::class,
            'nid_barang',
            'nid'
        );
    }
}
