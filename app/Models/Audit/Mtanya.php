<?php

namespace App\Models\Audit;

use App\Models\Auth\Mdepartemen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mtanya extends Model
{
    use HasFactory;

    protected $table = 'mtanya';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'nid_kat',
        'ctanya',
        'cquest',
        'nurut',
        'nsequence',
        'factive',
        'created_at',
    ];

    protected $casts = [
        'nid_kat'    => 'integer',
        'nurut'      => 'integer',
        'factive'    => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function getCquestAttribute()
    {
        return $this->ctanya ?? ($this->attributes['cquest'] ?? null);
    }

    public function setCquestAttribute($value)
    {
        $this->attributes['ctanya'] = $value;
    }

    public function getNsequenceAttribute()
    {
        return $this->nurut ?? ($this->attributes['nsequence'] ?? null);
    }

    public function setNsequenceAttribute($value)
    {
        $this->attributes['nurut'] = $value;
    }

    /**
     * Kategori pertanyaan
     */
    public function category()
    {
        return $this->belongsTo(MkatTanya::class, 'nid_kat', 'nid');
    }

    /**
     * Scope hanya pertanyaan aktif
     */
    public function scopeActive(Builder $query)
    {
        return $query->where('factive', 1);
    }

    /**
     * Responses yang menggunakan pertanyaan ini
     */
    public function responses()
    {
        return $this->hasMany(TauditHasil::class, 'nid_tanya', 'nid');
    }

    /**
     * Department yang di-mapping dengan pertanyaan ini
     */
    public function departments()
    {
        return $this->belongsToMany(Mdepartemen::class, 'tdept_tanya', 'nid_tanya', 'nid_dept');
    }
}

