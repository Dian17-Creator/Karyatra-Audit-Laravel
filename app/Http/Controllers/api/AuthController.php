<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auth\Muser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Process User Login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $email = strtolower(trim($request->email));
        $user = Muser::where('cemail', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User ID atau password salah.'
            ], 401);
        }

        // Cek apakah akun nonaktif (dnonactive IS NOT NULL)
        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Akun sudah tidak aktif.'
            ], 403);
        }

        // Password check (supports Hash::check and plain text fallback)
        $passwordMatches = Hash::check($request->password, $user->cpassword) || ($request->password === $user->cpassword);

        if (!$passwordMatches) {
            return response()->json([
                'success' => false,
                'message' => 'User ID atau password salah.'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => [
                'id'                   => $user->nid,
                'email'                => $user->cemail,
                'name'                 => $user->cnamalengkap,
                'company'              => $user->cperusahaan,
                'level'                => $user->clevel,
                'fowner'               => (bool) $user->fowner,
                'is_owner'             => $user->isOwner(),
                'is_active'            => $user->isActive(),
                'dnonactive'           => $user->dnonactive ? $user->dnonactive->format('Y-m-d') : null,
                'demailverified'       => $user->demailverified ? $user->demailverified->format('Y-m-d H:i:s') : null,
                'ntrialauditcreated'   => $user->ntrialauditcreated,
                'ntrialopnamecreated'  => $user->ntrialopnamecreated,
                'role' => [
                    'admin' => $user->isAdmin(),
                    'audit' => $user->isAudit(),
                    'owner' => $user->isOwner(),
                ]
            ]
        ]);
    }

    /**
     * Register New User / Company Owner (Auditra Style)
     */
    public function register(Request $request)
    {
        // Support field names from both Auditra web form and API JSON
        $fullName = trim($request->input('register_name') ?? $request->input('namalengkap') ?? '');
        $company = trim($request->input('register_company') ?? $request->input('perusahaan') ?? '');
        $company = preg_replace('/\s+/', ' ', $company);
        $email = strtolower(trim($request->input('register_email') ?? $request->input('email') ?? ''));
        $password = $request->input('register_password') ?? $request->input('password') ?? '';

        $dataToValidate = [
            'email'       => $email,
            'namalengkap' => $fullName,
            'perusahaan'  => $company,
            'password'    => $password,
        ];

        $validator = Validator::make($dataToValidate, [
            'email'        => 'required|email|max:255',
            'namalengkap'  => 'required|string|max:100',
            'perusahaan'   => 'required|string|max:200',
            'password'     => 'required|string|min:8',
        ], [
            'email.required'       => 'Email wajib diisi.',
            'email.email'          => 'Format email tidak valid.',
            'namalengkap.required' => 'Nama lengkap wajib diisi.',
            'perusahaan.required'  => 'Nama perusahaan wajib diisi.',
            'password.required'    => 'Password wajib diisi.',
            'password.min'         => 'Password minimal 8 karakter.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors()
            ], 422);
        }

        // 1. Cek Email unik
        $existingEmail = Muser::where('cemail', $email)->first();
        if ($existingEmail) {
            return response()->json([
                'success' => false,
                'message' => 'Email sudah terdaftar.'
            ], 422);
        }

        // 2. Cek Perusahaan unik (Case-Insensitive) - Auditra Rule
        $existingCompany = Muser::whereRaw('LOWER(TRIM(cperusahaan)) = ?', [strtolower($company)])->first();
        if ($existingCompany) {
            return response()->json([
                'success' => false,
                'message' => 'Perusahaan tersebut sudah terdaftar. Silakan hubungi administrator perusahaan Anda.'
            ], 422);
        }

        // 3. Public registration creates an Account/Company Owner with admin level
        $user = Muser::create([
            'cemail'               => $email,
            'cnamalengkap'         => $fullName,
            'cperusahaan'          => $company,
            'cpassword'            => Hash::make($password),
            'fowner'               => 1,
            'clevel'               => 'admin',
            'dcreated'             => Carbon::now(),
            'demailverified'       => null,
            'ntrialauditcreated'   => 0,
            'ntrialopnamecreated'  => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dibuat.',
            'data'    => [
                'id'                   => $user->nid,
                'email'                => $user->cemail,
                'name'                 => $user->cnamalengkap,
                'company'              => $user->cperusahaan,
                'level'                => $user->clevel,
                'fowner'               => (bool) $user->fowner,
                'is_owner'             => $user->isOwner(),
                'role' => [
                    'admin' => $user->isAdmin(),
                    'audit' => $user->isAudit(),
                    'owner' => $user->isOwner(),
                ]
            ]
        ], 201);
    }

    /**
     * Get Current User Detail
     */
    public function me(Request $request)
    {
        $userId = $request->query('user_id') ?? $request->input('user_id');
        $email = $request->query('email') ?? $request->input('email');

        $query = Muser::query();

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

        $user = $query->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id'                   => $user->nid,
                'email'                => $user->cemail,
                'name'                 => $user->cnamalengkap,
                'company'              => $user->cperusahaan,
                'level'                => $user->clevel,
                'fowner'               => (bool) $user->fowner,
                'is_owner'             => $user->isOwner(),
                'is_active'            => $user->isActive(),
                'dnonactive'           => $user->dnonactive ? $user->dnonactive->format('Y-m-d') : null,
                'demailverified'       => $user->demailverified ? $user->demailverified->format('Y-m-d H:i:s') : null,
                'ntrialauditcreated'   => $user->ntrialauditcreated,
                'ntrialopnamecreated'  => $user->ntrialopnamecreated,
                'role' => [
                    'admin' => $user->isAdmin(),
                    'audit' => $user->isAudit(),
                    'owner' => $user->isOwner(),
                ]
            ]
        ]);
    }
}
