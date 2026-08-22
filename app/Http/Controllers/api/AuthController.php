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

        $email = trim($request->email);
        $user = Muser::where('cemail', $email)->first();

        // User tidak ditemukan
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.'
            ], 401);
        }

        // Cek apakah akun nonaktif
        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Akun sudah tidak aktif.'
            ], 403);
        }

        // Password check (supports Hash::check and plain text fallback if any legacy)
        $passwordMatches = Hash::check($request->password, $user->cpassword) || ($request->password === $user->cpassword);

        if (!$passwordMatches) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.'
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
     * Register New User
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'        => 'required|email|unique:Muser,cemail',
            'namalengkap'  => 'required|string|max:255',
            'password'     => 'required|string|min:6',
            'perusahaan'   => 'nullable|string|max:255',
            'level'        => 'nullable|in:admin,audit',
            'fowner'       => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = Muser::create([
            'cemail'               => trim($request->email),
            'cnamalengkap'         => trim($request->namalengkap),
            'cpassword'            => Hash::make($request->password),
            'cperusahaan'          => $request->perusahaan ? trim($request->perusahaan) : null,
            'clevel'               => $request->level ?? 'audit',
            'fowner'               => $request->boolean('fowner', false),
            'dcreated'             => Carbon::now(),
            'ntrialauditcreated'   => 0,
            'ntrialopnamecreated'  => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil.',
            'data'    => [
                'id'          => $user->nid,
                'email'       => $user->cemail,
                'name'        => $user->cnamalengkap,
                'company'     => $user->cperusahaan,
                'level'       => $user->clevel,
                'is_owner'    => $user->isOwner(),
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
            $query->where('cemail', $email);
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
