<?php

namespace App\Models\Audit;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MkatTanya extends Model
{
    use HasFactory;

    protected $table = 'mkat_tanya';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'cnama',
        'cket',
        'cperusahaan',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];


    //Daftar pertanyaan dalam kategori
    public function questions()
    {
        return $this->hasMany(Mtanya::class, 'nid_kat', 'nid');
    }
}
