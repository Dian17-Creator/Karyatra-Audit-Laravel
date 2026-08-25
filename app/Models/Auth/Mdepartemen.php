<?php

namespace App\Models\Auth;

use App\Models\Audit\Maudit;
use App\Models\Audit\Mtanya;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mdepartemen extends Model
{
    use HasFactory;

    protected $table = 'mdepartemen';

    protected $primaryKey = 'nid';

    public $timestamps = false;

    protected $fillable = [
        'cnama',
        'dcreated',
        'cperusahaan',
    ];

    protected $casts = [
        'dcreated' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Semua user yang berada pada department ini
     */
    public function users()
    {
        return $this->hasMany(Muser::class, 'niddept', 'nid');
    }

    /**
     * Semua audit pada department ini
     */
    public function audits()
    {
        return $this->hasMany(Maudit::class, 'nid_dept', 'nid');
    }

    /**
     * Pertanyaan audit yang di-mapping ke department ini
     */
    public function auditQuestions()
    {
        return $this->belongsToMany(Mtanya::class, 'tdept_tanya', 'nid_dept', 'nid_tanya');
    }

    /**
     * Cek apakah departemen sedang digunakan di tabel transaksi/mapping
     */
    public function isInUse(): bool
    {
        return static::checkInUse($this->nid);
    }

    /**
     * Helper static untuk cek penggunaan departemen
     */
    public static function checkInUse(int $departmentId): bool
    {
        $tables = [
            'tdept_barang',
            'tdept_tanya',
            'maudit',
            'mopname'
        ];

        foreach ($tables as $table) {
            if (\Illuminate\Support\Facades\DB::table($table)->where('nid_dept', $departmentId)->exists()) {
                return true;
            }
        }

        return false;
    }
}
