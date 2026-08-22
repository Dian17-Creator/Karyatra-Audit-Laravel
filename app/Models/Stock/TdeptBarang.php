<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Auth\Mdepartemen;
use App\Models\Stock\Mbarang;

class Tdeptbarang extends Model
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
}
