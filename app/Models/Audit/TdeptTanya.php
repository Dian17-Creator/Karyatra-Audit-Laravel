<?php

namespace App\Models\Audit;

use App\Models\Auth\Mdepartemen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TdeptTanya extends Model
{
    use HasFactory;

    protected $table = 'tdept_tanya';

    protected $primaryKey = 'nid_dept';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'nid_dept',
        'nid_tanya',
    ];

    public function department()
    {
        return $this->belongsTo(Mdepartemen::class, 'nid_dept', 'nid');
    }
}
