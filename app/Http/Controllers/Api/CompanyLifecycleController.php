<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auth\Muser;
use App\Models\Auth\TaccountLifecycleEmailLog;
use App\Mail\CompanyDeactivatedMail;
use App\Mail\CompanyReactivatedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CompanyLifecycleController extends Controller
{
    /**
     * Helper to retrieve user from request (via user_id, email, or request user)
     */
    protected function resolveUser(Request $request): ?Muser
    {
        $userId = $request->input('user_id') ?? $request->query('user_id') ?? $request->input('id');
        $email = strtolower(trim((string) ($request->input('email') ?? $request->query('email') ?? '')));

        if ($userId) {
            return Muser::where('nid', $userId)->first();
        }

        if (!empty($email)) {
            return Muser::where('cemail', $email)->first();
        }

        if ($request->user()) {
            return $request->user();
        }

        return null;
    }

    /**
     * 1. Get Company Lifecycle Status
     */
    public function status(Request $request)
    {
        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan. Parameter user_id atau email diperlukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status lifecycle perusahaan berhasil dimuat.',
            'data'    => $user->getCompanyLifecycleState(),
        ]);
    }

    /**
     * 2. Deactivate Company (Owner Only)
     */
    public function deactivate(Request $request)
    {
        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan. Parameter user_id atau email diperlukan.',
            ], 404);
        }

        if (!$user->isOwner()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Owner perusahaan yang dapat melakukan penonaktifan sementara.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'confirm'          => 'required|boolean',
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'confirm.required'          => 'Konfirmasi penonaktifan wajib diisi.',
            'confirm.boolean'           => 'Format konfirmasi tidak valid.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if (!$request->boolean('confirm')) {
            return response()->json([
                'success' => false,
                'message' => 'Konfirmasi penonaktifan harus disetujui (true).',
            ], 422);
        }

        $passwordMatches = Hash::check($request->current_password, $user->cpassword)
            || ($request->current_password === $user->cpassword);

        if (!$passwordMatches) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini tidak sesuai.',
            ], 422);
        }

        // Execution
        $user->dcompanynonactive = Carbon::now();
        $user->save();

        // Send Email & Log
        $logStatus = 'sent';
        $logError = null;

        try {
            Mail::to($user->cemail)->send(new CompanyDeactivatedMail($user));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email CompanyDeactivatedMail: ' . $e->getMessage());
            $logStatus = 'failed';
            $logError = $e->getMessage();
        }

        TaccountLifecycleEmailLog::create([
            'nid_owner_snapshot'   => $user->nid,
            'cevent'               => 'deactivated',
            'ccompany_snapshot'    => $user->cperusahaan,
            'cowner_name_snapshot' => $user->cnamalengkap,
            'cowner_email_snapshot' => $user->cemail,
            'cstatus'              => $logStatus,
            'cerror'               => $logError,
            'dsent'                => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perusahaan berhasil dinonaktifkan sementara.',
            'data'    => $user->getCompanyLifecycleState(),
        ]);
    }

    /**
     * 3. Reactivate Company (Owner Only)
     */
    public function reactivate(Request $request)
    {
        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan. Parameter user_id atau email diperlukan.',
            ], 404);
        }

        if (!$user->isOwner()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Owner perusahaan yang dapat mengaktifkan kembali perusahaan.',
            ], 403);
        }

        if ($user->isDeletionPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Perusahaan sedang dalam proses penghapusan. Batalkan penghapusan terlebih dahulu.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $passwordMatches = Hash::check($request->current_password, $user->cpassword)
            || ($request->current_password === $user->cpassword);

        if (!$passwordMatches) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini tidak sesuai.',
            ], 422);
        }

        // Execution
        $user->dcompanynonactive = null;
        $user->save();

        // Send Email & Log
        $logStatus = 'sent';
        $logError = null;

        try {
            Mail::to($user->cemail)->send(new CompanyReactivatedMail($user));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email CompanyReactivatedMail: ' . $e->getMessage());
            $logStatus = 'failed';
            $logError = $e->getMessage();
        }

        TaccountLifecycleEmailLog::create([
            'nid_owner_snapshot'   => $user->nid,
            'cevent'               => 'reactivated',
            'ccompany_snapshot'    => $user->cperusahaan,
            'cowner_name_snapshot' => $user->cnamalengkap,
            'cowner_email_snapshot' => $user->cemail,
            'cstatus'              => $logStatus,
            'cerror'               => $logError,
            'dsent'                => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perusahaan berhasil diaktifkan kembali.',
            'data'    => $user->getCompanyLifecycleState(),
        ]);
    }
}
