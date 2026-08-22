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
        'nid_kat'    => 'integer',
        'nurut'  => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Relasi ke grup audit.
     */
    public function group()
    {
        return $this->belongsTo(MkatBarang::class, 'nid_kat', 'nid');
    }

    /**
     * Relasi ke respons audit/opname.
     */
    public function invresps()
    {
        return $this->hasMany(TopnameHasil::class, 'nid_item', 'nid');
    }
}
