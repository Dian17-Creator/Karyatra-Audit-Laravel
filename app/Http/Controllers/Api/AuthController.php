<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auth\Muser;
use App\Mail\VerifyEmailMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
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
                'department_id'        => $user->niddept,
                'company'              => $user->cperusahaan,
                'level'                => $user->clevel,
                'fowner'               => (bool) $user->fowner,
                'is_owner'             => $user->isOwner(),
                'is_active'            => $user->isActive(),
                'is_email_verified'    => $user->isEmailVerified(),
                'is_trial'             => $user->isTrial(),
                'dnonactive'           => $user->dnonactive ? $user->dnonactive->format('Y-m-d') : null,
                'demailverified'       => $user->demailverified ? $user->demailverified->format('Y-m-d H:i:s') : null,
                'ntrialauditcreated'   => $user->ntrialauditcreated,
                'ntrialopnamecreated'  => $user->ntrialopnamecreated,
                'role' => [
                    'admin' => $user->isAdmin(),
                    'audit' => $user->isAudit(),
                    'super' => $user->isOwner(),
                    'hrd'   => strtolower(trim((string) $user->clevel)) === 'hrd',
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

        // 1. Cek Email unik secara global
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

        // Issue verification token
        $token = $user->issueVerificationToken();

        // Wrap sending verification email with try-catch
        $mailSent = false;
        try {
            Mail::to($user->cemail)->send(new VerifyEmailMail($user, $token));
            $mailSent = true;
        } catch (\Throwable $e) {
            Log::error('Failed sending verification email on registration: ' . $e->getMessage());
            $mailSent = false;
        }

        $message = $mailSent
            ? "Akun berhasil dibuat. Link verifikasi telah dikirim ke " . $user->cemail
            : "Akun berhasil dibuat, tetapi email verifikasi belum dapat dikirim. Silakan kirim ulang dari aplikasi.";

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => [
                'id'                   => $user->nid,
                'email'                => $user->cemail,
                'name'                 => $user->cnamalengkap,
                'department_id'        => $user->niddept,
                'company'              => $user->cperusahaan,
                'level'                => $user->clevel,
                'fowner'               => (bool) $user->fowner,
                'is_owner'             => $user->isOwner(),
                'is_email_verified'    => $user->isEmailVerified(),
                'is_trial'             => $user->isTrial(),
                'role' => [
                    'admin' => $user->isAdmin(),
                    'audit' => $user->isAudit(),
                    'super' => $user->isOwner(),
                    'hrd'   => strtolower(trim((string) $user->clevel)) === 'hrd',
                    'owner' => $user->isOwner(),
                ]
            ]
        ], 201);
    }

    /**
     * Verify Company Email via Token
     */
    public function verifyEmail(Request $request)
    {
        $token = $request->query('token') ?? $request->input('token');

        if (!$token || !is_string($token)) {
            return $this->respondVerificationResult($request, false, 'Link verifikasi tidak valid.', 400);
        }

        $tokenHash = hash('sha256', $token);

        $user = Muser::where('cverifytokenhash', $tokenHash)
            ->where('fowner', 1)
            ->whereNull('dnonactive')
            ->first();

        if (!$user) {
            return $this->respondVerificationResult($request, false, 'Link verifikasi tidak valid.', 404);
        }

        // Jika demailverified sudah terisi
        if ($user->isEmailVerified()) {
            return $this->respondVerificationResult($request, true, 'Email perusahaan ini sudah terverifikasi.', 200, $user);
        }

        // Jika dverifyexpires < Carbon::now() (Expired > 48 jam)
        if ($user->dverifyexpires && Carbon::now()->greaterThan($user->dverifyexpires)) {
            $user->cverifytokenhash = null;
            $user->dverifyexpires = null;
            $user->save();

            return $this->respondVerificationResult($request, false, 'Link verifikasi kedaluwarsa (lebih dari 48 jam). Silakan kirim ulang.', 400);
        }

        // Set email verified
        $user->demailverified = Carbon::now();
        $user->cverifytokenhash = null;
        $user->dverifyexpires = null;
        $user->save();

        return $this->respondVerificationResult($request, true, 'Email berhasil diverifikasi. Akun perusahaan keluar dari mode trial.', 200, $user);
    }

    /**
     * Resend Email Verification Token
     */
    public function resendVerification(Request $request)
    {
        $userId = $request->input('user_id') ?? $request->query('user_id');
        $email = strtolower(trim($request->input('email') ?? $request->query('email') ?? ''));

        $query = Muser::where('fowner', 1)->whereNull('dnonactive');

        if ($userId) {
            $query->where('nid', $userId);
        } elseif ($email) {
            $query->where('cemail', $email);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Parameter user_id atau email diperlukan.'
            ], 422);
        }

        $user = $query->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User owner tidak ditemukan atau akun tidak aktif.'
            ], 404);
        }

        if ($user->isEmailVerified()) {
            return response()->json([
                'success' => false,
                'message' => 'Email perusahaan ini sudah terverifikasi.'
            ], 400);
        }

        // Issue new token
        $token = $user->issueVerificationToken();

        // Send email
        try {
            Mail::to($user->cemail)->send(new VerifyEmailMail($user, $token));
            return response()->json([
                'success' => true,
                'message' => 'Link verifikasi telah dikirim ulang ke ' . $user->cemail
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Failed resending verification email: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'message' => 'Token verifikasi diperbarui, tetapi email verifikasi gagal dikirim. Silakan coba beberapa saat lagi.'
            ], 200);
        }
    }

    /**
     * Helper to respond formatted JSON or HTML status page for email verification
     */
    private function respondVerificationResult(Request $request, bool $success, string $message, int $status = 200, ?Muser $user = null)
    {
        if ($request->wantsJson() || $request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'success' => $success,
                'message' => $message,
                'data'    => $user ? [
                    'id'                => $user->nid,
                    'email'             => $user->cemail,
                    'name'              => $user->cnamalengkap,
                    'company'           => $user->cperusahaan,
                    'is_email_verified' => $user->isEmailVerified(),
                    'is_trial'          => $user->isTrial(),
                    'demailverified'    => $user->demailverified ? $user->demailverified->format('Y-m-d H:i:s') : null,
                ] : null
            ], $status);
        }

        return response()->view('emails.verification-status', [
            'success' => $success,
            'message' => $message,
            'user'    => $user,
        ], $status);
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
                'department_id'        => $user->niddept,
                'company'              => $user->cperusahaan,
                'level'                => $user->clevel,
                'fowner'               => (bool) $user->fowner,
                'is_owner'             => $user->isOwner(),
                'is_active'            => $user->isActive(),
                'is_email_verified'    => $user->isEmailVerified(),
                'is_trial'             => $user->isTrial(),
                'dnonactive'           => $user->dnonactive ? $user->dnonactive->format('Y-m-d') : null,
                'demailverified'       => $user->demailverified ? $user->demailverified->format('Y-m-d H:i:s') : null,
                'ntrialauditcreated'   => $user->ntrialauditcreated,
                'ntrialopnamecreated'  => $user->ntrialopnamecreated,
                'role' => [
                    'admin' => $user->isAdmin(),
                    'audit' => $user->isAudit(),
                    'super' => $user->isOwner(),
                    'hrd'   => strtolower(trim((string) $user->clevel)) === 'hrd',
                    'owner' => $user->isOwner(),
                ]
            ]
        ]);
    }
}
