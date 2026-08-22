<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Stock\Mbarang;

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
    ];

    protected $casts = [
        'nid_opname'   => 'integer',
        'nid_barang'    => 'integer',
        'nqty_stock'  => 'decimal:2',
        'nqty_real'   => 'decimal:2',
        'nselisih'       => 'decimal:2',
        'nsel_lebih'  => 'decimal:2',
        'nsel_kurang' => 'decimal:2',
        'fna'         => 'boolean',
        'updated_at'  => 'datetime',
    ];

    /**
     * Relasi ke item audit.
     */
    public function item()
    {
        return $this->belongsTo(Mbarang::class, 'nid_item', 'nid');
    }
}
