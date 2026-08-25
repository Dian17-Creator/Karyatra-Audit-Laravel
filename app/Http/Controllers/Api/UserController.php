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

class UserController extends Controller
{
    /**
     * Update Profile (Nama Lengkap & Email)
     */
    public function updateProfile(Request $request)
    {
        $userId = $request->input('user_id') ?? $request->input('id');
        $fullName = trim($request->input('full_name') ?? $request->input('namalengkap') ?? $request->input('name') ?? '');
        $email = strtolower(trim($request->input('email') ?? $request->input('cemail') ?? ''));

        $validator = Validator::make([
            'user_id'   => $userId,
            'full_name' => $fullName,
            'email'     => $email,
        ], [
            'user_id'   => 'required|integer',
            'full_name' => 'required|string|max:100',
            'email'     => 'required|email|max:255',
        ], [
            'user_id.required'   => 'ID pengguna diperlukan.',
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'email.required'     => 'Email wajib diisi.',
            'email.email'        => 'Format email tidak valid.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = Muser::where('nid', $userId)->whereNull('dnonactive')->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan atau tidak aktif.'
            ], 404);
        }

        $emailChanged = strtolower(trim((string) $user->cemail)) !== $email;

        // Proteksi Owner Terverifikasi: Email tidak dapat diubah
        if ($user->isOwner() && $user->isEmailVerified() && $emailChanged) {
            return response()->json([
                'success' => false,
                'message' => 'Email owner yang sudah terverifikasi tidak dapat diubah.'
            ], 422);
        }

        // Cek keunikan email global
        if ($emailChanged) {
            $existingEmail = Muser::where('cemail', $email)->where('nid', '!=', $user->nid)->first();
            if ($existingEmail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email sudah digunakan oleh pengguna lain.'
                ], 422);
            }
        }

        $user->cnamalengkap = $fullName;

        $reverificationIssued = false;
        $mailSent = false;

        if ($emailChanged) {
            $user->cemail = $email;

            // Jika Owner mengubah email sebelum terverifikasi, reset verifikasi & kirim ulang token
            if ($user->isOwner()) {
                $user->demailverified = null;
                $user->cverifytokenhash = null;
                $user->dverifyexpires = null;
                $user->save();

                $token = $user->issueVerificationToken();
                $reverificationIssued = true;

                try {
                    Mail::to($user->cemail)->send(new VerifyEmailMail($user, $token));
                    $mailSent = true;
                } catch (\Throwable $e) {
                    Log::error('Gagal mengirim email verifikasi saat update profil owner: ' . $e->getMessage());
                    $mailSent = false;
                }
            } else {
                $user->save();
            }
        } else {
            $user->save();
        }

        $message = 'Profil berhasil diperbarui.';
        if ($reverificationIssued) {
            $message .= $mailSent
                ? ' Link verifikasi baru telah dikirim ke ' . $user->cemail
                : ' Link verifikasi telah diperbarui, tetapi email verifikasi gagal dikirim. Silakan kirim ulang dari aplikasi.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $this->formatUserData($user)
        ]);
    }

    /**
     * Ganti Password Pengguna
     */
    public function changePassword(Request $request)
    {
        $userId = $request->input('user_id') ?? $request->input('id');
        $currentPassword = $request->input('current_password') ?? '';
        $newPassword = $request->input('new_password') ?? '';
        $confirmPassword = $request->input('confirm_password') ?? $request->input('new_password_confirmation') ?? '';

        $validator = Validator::make([
            'user_id'          => $userId,
            'current_password' => $currentPassword,
            'new_password'     => $newPassword,
            'confirm_password' => $confirmPassword,
        ], [
            'user_id'          => 'required|integer',
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8',
            'confirm_password' => 'required|string|same:new_password',
        ], [
            'user_id.required'          => 'ID pengguna diperlukan.',
            'current_password.required' => 'Password saat ini wajib diisi.',
            'new_password.required'     => 'Password baru wajib diisi.',
            'new_password.min'          => 'Password baru minimal 8 karakter.',
            'confirm_password.required' => 'Konfirmasi password baru wajib diisi.',
            'confirm_password.same'     => 'Konfirmasi password baru tidak sama.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = Muser::where('nid', $userId)->whereNull('dnonactive')->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan atau tidak aktif.'
            ], 404);
        }

        // Cek password lama (Hash::check atau plaintext match)
        $passwordMatches = Hash::check($currentPassword, $user->cpassword) || ($currentPassword === $user->cpassword);

        if (!$passwordMatches) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini tidak benar.'
            ], 422);
        }

        $user->cpassword = Hash::make($newPassword);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui.'
        ]);
    }

    /**
     * List Seluruh Pengguna dalam Perusahaan yang Sama
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

        $users = Muser::where('cperusahaan', $company)
            ->whereNull('dnonactive')
            ->orderBy('fowner', 'desc')
            ->orderBy('cnamalengkap', 'asc')
            ->get();

        $formattedUsers = $users->map(function ($u) {
            return $this->formatUserData($u);
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar pengguna berhasil dimuat.',
            'data'    => $formattedUsers
        ]);
    }

    /**
     * Tambah Pengguna Baru atau Reaktivasi Pengguna Lama (Owner Only)
     */
    public function store(Request $request)
    {
        $ownerId = $request->input('owner_id') ?? $request->input('user_id');
        $fullName = trim($request->input('new_name') ?? $request->input('namalengkap') ?? $request->input('name') ?? '');
        $email = strtolower(trim($request->input('new_email') ?? $request->input('email') ?? ''));
        $password = $request->input('new_user_password') ?? $request->input('password') ?? '';
        $level = strtolower(trim($request->input('new_level') ?? $request->input('level') ?? 'audit'));

        if (!in_array($level, ['admin', 'audit'], true)) {
            $level = 'audit';
        }

        $validator = Validator::make([
            'owner_id'  => $ownerId,
            'full_name' => $fullName,
            'email'     => $email,
            'password'  => $password,
            'level'     => $level,
        ], [
            'owner_id'  => 'required|integer',
            'full_name' => 'required|string|max:100',
            'email'     => 'required|email|max:255',
            'password'  => 'required|string|min:8',
            'level'     => 'required|in:admin,audit',
        ], [
            'owner_id.required'  => 'ID owner diperlukan.',
            'full_name.required' => 'Nama pengguna wajib diisi.',
            'email.required'     => 'Format email tidak valid.',
            'email.email'        => 'Format email tidak valid.',
            'password.required'  => 'Password minimal 8 karakter.',
            'password.min'       => 'Password minimal 8 karakter.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $owner = Muser::where('nid', $ownerId)->whereNull('dnonactive')->first();

        if (!$owner || !$owner->isOwner()) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur ini hanya dapat diakses oleh Pemilik (Owner) perusahaan.'
            ], 403);
        }

        // Cek Mode Trial: Tambah pengguna dinonaktifkan saat trial
        if ($owner->isTrial()) {
            return response()->json([
                'success' => false,
                'message' => 'Manajemen pengguna dinonaktifkan selama mode trial. Verifikasi email owner untuk membuka fitur ini.'
            ], 403);
        }

        $company = $owner->cperusahaan;

        // Cek eksistensi email
        $existingUser = Muser::where('cemail', $email)->first();

        if ($existingUser) {
            // Jika akun aktif
            if ($existingUser->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email sudah terdaftar.'
                ], 422);
            }

            // Jika akun terhapus tapi milik perusahaan lain
            if ($existingUser->cperusahaan !== $company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email sudah terdaftar pada perusahaan lain.'
                ], 422);
            }

            // Jika akun milik owner
            if ($existingUser->isOwner()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun pemilik tidak dapat diaktifkan melalui menu ini.'
                ], 422);
            }

            // Reaktivasi Pengguna dalam Perusahaan yang Sama
            $existingUser->cnamalengkap = $fullName;
            $existingUser->cpassword = Hash::make($password);
            $existingUser->clevel = $level;
            $existingUser->dnonactive = null;
            $existingUser->save();

            return response()->json([
                'success' => true,
                'message' => 'Pengguna berhasil diaktifkan kembali.',
                'data'    => $this->formatUserData($existingUser)
            ], 200);
        }

        // Buat Pengguna Baru
        $newUser = Muser::create([
            'cemail'              => $email,
            'cnamalengkap'        => $fullName,
            'cpassword'           => Hash::make($password),
            'cperusahaan'         => $company,
            'fowner'              => 0,
            'clevel'              => $level,
            'dcreated'            => Carbon::now(),
            'dnonactive'          => null,
            'ntrialauditcreated'  => 0,
            'ntrialopnamecreated' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengguna baru berhasil ditambahkan.',
            'data'    => $this->formatUserData($newUser)
        ], 201);
    }

    /**
     * Perbarui Level Akses Pengguna (Owner Only)
     */
    public function updateLevel(Request $request, $id = null)
    {
        $ownerId = $request->input('owner_id') ?? $request->input('user_id');
        $targetUserId = $id ?? $request->input('target_user_id');
        $level = strtolower(trim($request->input('clevel') ?? $request->input('level') ?? ''));

        if (!$targetUserId || (int)$targetUserId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak valid.'
            ], 422);
        }

        $owner = Muser::where('nid', $ownerId)->whereNull('dnonactive')->first();

        if (!$owner || !$owner->isOwner()) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur ini hanya dapat diakses oleh Pemilik (Owner) perusahaan.'
            ], 403);
        }

        if ($owner->isTrial()) {
            return response()->json([
                'success' => false,
                'message' => 'Manajemen pengguna dinonaktifkan selama mode trial. Verifikasi email owner untuk membuka fitur ini.'
            ], 403);
        }

        if ((int)$targetUserId === (int)$owner->nid) {
            return response()->json([
                'success' => false,
                'message' => 'Level akses pemilik tidak dapat diubah.'
            ], 422);
        }

        if (!in_array($level, ['admin', 'audit'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Level akses tidak valid.'
            ], 422);
        }

        $targetUser = Muser::where('nid', $targetUserId)
            ->where('cperusahaan', $owner->cperusahaan)
            ->where('fowner', 0)
            ->whereNull('dnonactive')
            ->first();

        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan atau tidak dapat diubah.'
            ], 404);
        }

        $targetUser->clevel = $level;
        $targetUser->save();

        return response()->json([
            'success' => true,
            'message' => 'Level akses berhasil diperbarui.',
            'data'    => $this->formatUserData($targetUser)
        ]);
    }

    /**
     * Soft Delete / Deaktivasi Pengguna (Owner Only)
     */
    public function destroy(Request $request, $id = null)
    {
        $ownerId = $request->input('owner_id') ?? $request->input('user_id');
        $targetUserId = $id ?? $request->input('target_user_id');

        if (!$targetUserId || (int)$targetUserId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak valid.'
            ], 422);
        }

        $owner = Muser::where('nid', $ownerId)->whereNull('dnonactive')->first();

        if (!$owner || !$owner->isOwner()) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur ini hanya dapat diakses oleh Pemilik (Owner) perusahaan.'
            ], 403);
        }

        if ($owner->isTrial()) {
            return response()->json([
                'success' => false,
                'message' => 'Manajemen pengguna dinonaktifkan selama mode trial. Verifikasi email owner untuk membuka fitur ini.'
            ], 403);
        }

        if ((int)$targetUserId === (int)$owner->nid) {
            return response()->json([
                'success' => false,
                'message' => 'Pemilik perusahaan tidak dapat menghapus dirinya sendiri.'
            ], 422);
        }

        $targetUser = Muser::where('nid', $targetUserId)
            ->where('cperusahaan', $owner->cperusahaan)
            ->where('fowner', 0)
            ->whereNull('dnonactive')
            ->first();

        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan atau tidak dapat dihapus.'
            ], 404);
        }

        // Soft Delete: dnonactive = CURRENT_DATE / NOW
        $targetUser->dnonactive = Carbon::now();
        $targetUser->save();

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil dinonaktifkan.'
        ]);
    }

    /**
     * Format Data User untuk Output JSON Standard
     */
    private function formatUserData(Muser $user): array
    {
        return [
            'id'                => $user->nid,
            'email'             => $user->cemail,
            'name'              => $user->cnamalengkap,
            'company'           => $user->cperusahaan,
            'level'             => $user->clevel,
            'fowner'            => (bool) $user->fowner,
            'is_owner'          => $user->isOwner(),
            'is_active'         => $user->isActive(),
            'is_email_verified' => $user->isEmailVerified(),
            'is_trial'          => $user->isTrial(),
            'created_at'        => $user->dcreated ? $user->dcreated->format('Y-m-d H:i:s') : null,
            'dnonactive'        => $user->dnonactive ? $user->dnonactive->format('Y-m-d') : null,
            'action_type'       => $user->isOwner() ? 'owner_only' : 'manageable',
            'role'              => [
                'admin' => $user->isAdmin(),
                'audit' => $user->isAudit(),
                'super' => $user->isOwner(),
                'owner' => $user->isOwner(),
            ]
        ];
    }
}
