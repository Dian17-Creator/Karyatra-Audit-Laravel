<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MkatBarang extends Model
{
    use HasFactory;

    protected $table = 'mkat_barang';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'cnama',
        'cket',
        'cperusahaan',
    ];

    protected $casts = [
        'nid' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Relasi ke barang.
     */
    public function items()
    {
        return $this->hasMany(Mbarang::class, 'nid_kat', 'nid');
    }
}
