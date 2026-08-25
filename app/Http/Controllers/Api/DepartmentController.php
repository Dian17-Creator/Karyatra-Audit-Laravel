<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auth\Mdepartemen;
use App\Models\Auth\Muser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class DepartmentController extends Controller
{
    /**
     * Constant batas jumlah departemen untuk mode trial
     */
    const TRIAL_DEPARTMENT_LIMIT = 1;

    /**
     * List Seluruh Departemen milik Perusahaan
     */
    public function index(Request $request)
    {
        $userId = $request->query('user_id') ?? $request->input('user_id');
        $email = $request->query('email') ?? $request->input('email');

        $query = Muser::whereNull('dnonactive');

        if ($userId) {
            $query->where('nid', $userId);
        } elseif ($email) {
            $query->where('cemail', strtolower(trim($email)));
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Parameter user_id atau email diperlukan.'
            ], 400);
        }

        $currentUser = $query->first();

        if (!$currentUser) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan.'
            ], 404);
        }

        $company = $currentUser->cperusahaan;

        $departments = Mdepartemen::where('cperusahaan', $company)
            ->orderBy('cnama', 'asc')
            ->get();

        $formattedDepartments = $departments->map(function ($dept) {
            $inUse = $dept->isInUse();
            return [
                'id'         => $dept->nid,
                'name'       => $dept->cnama,
                'company'    => $dept->cperusahaan,
                'created_at' => $dept->dcreated ? $dept->dcreated->format('Y-m-d H:i:s') : null,
                'in_use'     => $inUse,
                'status'     => $inUse ? 'Digunakan' : 'Tersedia',
            ];
        });

        $isTrial = $currentUser->isTrial();
        $trialLimitReached = $isTrial && count($departments) >= self::TRIAL_DEPARTMENT_LIMIT;

        return response()->json([
            'success' => true,
            'message' => 'Daftar departemen berhasil dimuat.',
            'data'    => $formattedDepartments,
            'meta'    => [
                'total'                     => count($departments),
                'is_trial'                  => $isTrial,
                'trial_limit_reached'       => $trialLimitReached,
                'trial_department_limit'    => self::TRIAL_DEPARTMENT_LIMIT,
            ]
        ]);
    }

    /**
     * Tambah Departemen Baru
     */
    public function store(Request $request)
    {
        $userId = $request->input('user_id') ?? $request->input('owner_id');
        $name = trim($request->input('name') ?? $request->input('cnama') ?? '');
        $name = preg_replace('/\s+/', ' ', $name);

        $validator = Validator::make([
            'user_id' => $userId,
            'name'    => $name,
        ], [
            'user_id' => 'required|integer',
            'name'    => 'required|string|max:255',
        ], [
            'user_id.required' => 'ID pengguna diperlukan.',
            'name.required'    => 'Nama departemen / divisi wajib diisi.',
            'name.max'         => 'Nama departemen maksimal 255 karakter.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $currentUser = Muser::where('nid', $userId)->whereNull('dnonactive')->first();

        if (!$currentUser) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan.'
            ], 404);
        }

        // Cek Hak Akses: Hanya Owner / Admin
        if (!$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengelola departemen.'
            ], 403);
        }

        $company = $currentUser->cperusahaan;

        // Cek Batasan Mode Trial (Maksimal 1 Departemen)
        if ($currentUser->isTrial()) {
            $currentCount = Mdepartemen::where('cperusahaan', $company)->count();
            if ($currentCount >= self::TRIAL_DEPARTMENT_LIMIT) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batas trial tercapai. Perusahaan yang belum terverifikasi hanya dapat memiliki 1 departemen/divisi. Verifikasi email owner untuk menambah departemen.'
                ], 403);
            }
        }

        // Cek Duplikasi Nama Departemen dalam Perusahaan yang sama (Case-insensitive)
        $existingDept = Mdepartemen::where('cperusahaan', $company)
            ->whereRaw('LOWER(TRIM(cnama)) = ?', [strtolower($name)])
            ->first();

        if ($existingDept) {
            return response()->json([
                'success' => false,
                'message' => 'Departemen tersebut sudah terdaftar.'
            ], 422);
        }

        $department = Mdepartemen::create([
            'cnama'       => $name,
            'cperusahaan' => $company,
            'dcreated'    => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Departemen berhasil ditambahkan.',
            'data'    => [
                'id'         => $department->nid,
                'name'       => $department->cnama,
                'company'    => $department->cperusahaan,
                'created_at' => $department->dcreated ? $department->dcreated->format('Y-m-d H:i:s') : null,
                'in_use'     => false,
                'status'     => 'Tersedia',
            ]
        ], 201);
    }

    /**
     * Edit / Update Nama Departemen
     */
    public function update(Request $request, $id = null)
    {
        $userId = $request->input('user_id') ?? $request->input('owner_id');
        $departmentId = $id ?? $request->input('department_id') ?? $request->input('id');
        $name = trim($request->input('name') ?? $request->input('cnama') ?? '');
        $name = preg_replace('/\s+/', ' ', $name);

        if (!$departmentId || (int)$departmentId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Departemen tidak valid.'
            ], 422);
        }

        $validator = Validator::make([
            'user_id' => $userId,
            'name'    => $name,
        ], [
            'user_id' => 'required|integer',
            'name'    => 'required|string|max:255',
        ], [
            'user_id.required' => 'ID pengguna diperlukan.',
            'name.required'    => 'Nama departemen / divisi wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $currentUser = Muser::where('nid', $userId)->whereNull('dnonactive')->first();

        if (!$currentUser || !$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengelola departemen.'
            ], 403);
        }

        $company = $currentUser->cperusahaan;

        $department = Mdepartemen::where('nid', $departmentId)
            ->where('cperusahaan', $company)
            ->first();

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Departemen tidak ditemukan.'
            ], 404);
        }

        // Cek apakah departemen sedang digunakan di transaksi/mapping
        if ($department->isInUse()) {
            return response()->json([
                'success' => false,
                'message' => 'Departemen tidak dapat diubah karena sudah digunakan.'
            ], 422);
        }

        // Cek Duplikasi Nama dengan departemen lain
        $existingDept = Mdepartemen::where('cperusahaan', $company)
            ->whereRaw('LOWER(TRIM(cnama)) = ?', [strtolower($name)])
            ->where('nid', '!=', $department->nid)
            ->first();

        if ($existingDept) {
            return response()->json([
                'success' => false,
                'message' => 'Nama departemen tersebut sudah digunakan.'
            ], 422);
        }

        $department->cnama = $name;
        $department->save();

        return response()->json([
            'success' => true,
            'message' => 'Departemen berhasil diperbarui.',
            'data'    => [
                'id'         => $department->nid,
                'name'       => $department->cnama,
                'company'    => $department->cperusahaan,
                'created_at' => $department->dcreated ? $department->dcreated->format('Y-m-d H:i:s') : null,
                'in_use'     => false,
                'status'     => 'Tersedia',
            ]
        ]);
    }

    /**
     * Hapus Fisik Departemen (Hanya jika belum pernah digunakan)
     */
    public function destroy(Request $request, $id = null)
    {
        $userId = $request->input('user_id') ?? $request->input('owner_id');
        $departmentId = $id ?? $request->input('department_id') ?? $request->input('id');

        if (!$departmentId || (int)$departmentId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Departemen tidak valid.'
            ], 422);
        }

        $currentUser = Muser::where('nid', $userId)->whereNull('dnonactive')->first();

        if (!$currentUser || !$currentUser->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengelola departemen.'
            ], 403);
        }

        $company = $currentUser->cperusahaan;

        $department = Mdepartemen::where('nid', $departmentId)
            ->where('cperusahaan', $company)
            ->first();

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Departemen tidak ditemukan.'
            ], 404);
        }

        // Cek apakah departemen sedang digunakan di transaksi/mapping
        if ($department->isInUse()) {
            return response()->json([
                'success' => false,
                'message' => 'Departemen tidak dapat dihapus karena sudah digunakan.'
            ], 422);
        }

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Departemen berhasil dihapus.'
        ]);
    }
}
