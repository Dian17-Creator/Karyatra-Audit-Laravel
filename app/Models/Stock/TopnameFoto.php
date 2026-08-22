<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

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
}
