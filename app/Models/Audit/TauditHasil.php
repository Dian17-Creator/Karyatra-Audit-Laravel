<?php

namespace App\Models\Audit;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TauditHasil extends Model
{
    use HasFactory;

    protected $table = 'taudit_hasil';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'nid_audit',
        'nid_tanya',
        'nid_quest',
        'nnilai',
        'fna',
        'cket',
        'updated_at',
    ];

    protected $casts = [
        'nid_audit'  => 'integer',
        'nid_tanya'  => 'integer',
        'nnilai'     => 'decimal:1',
        'fna'        => 'boolean',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function getNidQuestAttribute()
    {
        return $this->nid_tanya ?? ($this->attributes['nid_quest'] ?? null);
    }

    public function setNidQuestAttribute($value)
    {
        $this->attributes['nid_tanya'] = $value;
    }


    /**
     * Header Audit
     */
    public function audit()
    {
        return $this->belongsTo(Maudit::class, 'nid_audit', 'nid');
    }

    /**
     * Pertanyaan Audit
     */
    public function question()
    {
        return $this->belongsTo(Mtanya::class, 'nid_tanya', 'nid');
    }

    /**
     * Foto-foto untuk response ini
     */
    public function photos()
    {
        return $this->hasMany(TauditFoto::class, 'nid_hasil', 'nid');
    }
}
