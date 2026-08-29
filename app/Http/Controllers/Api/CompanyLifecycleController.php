<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auth\Muser;
use App\Models\Auth\TaccountLifecycleEmailLog;
use App\Models\Subscription\Tsubscription;
use App\Mail\CompanyDeactivatedMail;
use App\Mail\CompanyReactivatedMail;
use App\Mail\CompanyDeletionRequestedMail;
use App\Mail\CompanyDeletionCancelledMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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

    /**
     * 4. Request Company Deletion (Owner Only)
     */
    public function requestDeletion(Request $request)
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
                'message' => 'Hanya Owner perusahaan yang dapat menjadwalkan penghapusan perusahaan.',
            ], 403);
        }

        // Syarat awal: Cek pengajuan langganan pending
        $hasPendingSubscription = Tsubscription::where('nid_owner', $user->nid)
            ->where('cstatus', 'pending')
            ->exists();

        if ($hasPendingSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'Masih ada pengajuan langganan yang menunggu keputusan Finance. Selesaikan proses tersebut sebelum menghapus perusahaan.',
            ], 400);
        }

        // Cek apakah langganan Pro sedang aktif
        $hasActivePro = Tsubscription::where('nid_owner', $user->nid)
            ->where('cstatus', 'approved')
            ->where('dend', '>', Carbon::now())
            ->exists();

        $rules = [
            'company_name'              => 'required|string',
            'current_password'          => 'required|string',
            'confirm_deletion'          => 'required|boolean',
            'confirm_finance_retention' => 'required|boolean',
        ];

        if ($hasActivePro) {
            $rules['confirm_pro_no_refund'] = 'required|boolean';
        }

        $validator = Validator::make($request->all(), $rules, [
            'company_name.required'              => 'Nama perusahaan wajib diisi.',
            'current_password.required'          => 'Password saat ini wajib diisi.',
            'confirm_deletion.required'          => 'Konfirmasi penghapusan wajib diisi.',
            'confirm_finance_retention.required' => 'Konfirmasi retensi data keuangan wajib diisi.',
            'confirm_pro_no_refund.required'     => 'Konfirmasi masa Pro tanpa refund wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Validasi pencocokan nama perusahaan
        if (strtolower(trim($request->company_name)) !== strtolower(trim((string) $user->cperusahaan))) {
            return response()->json([
                'success' => false,
                'message' => 'Nama perusahaan tidak sesuai dengan data terdaftar.',
            ], 422);
        }

        // Validasi password
        $passwordMatches = Hash::check($request->current_password, $user->cpassword)
            || ($request->current_password === $user->cpassword);

        if (!$passwordMatches) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini tidak sesuai.',
            ], 422);
        }

        if (!$request->boolean('confirm_deletion') || !$request->boolean('confirm_finance_retention')) {
            return response()->json([
                'success' => false,
                'message' => 'Semua pernyataan konfirmasi penghapusan harus disetujui (true).',
            ], 422);
        }

        if ($hasActivePro && !$request->boolean('confirm_pro_no_refund')) {
            return response()->json([
                'success' => false,
                'message' => 'Konfirmasi hangusnya sisa masa Pro tanpa refund harus disetujui.',
            ], 422);
        }

        // DB Transaction dengan Row Lock FOR UPDATE
        $owner = null;
        DB::transaction(function () use ($user, &$owner) {
            $owner = Muser::where('nid', $user->nid)->lockForUpdate()->first();

            $gracePeriod = $owner->isTrial()
                ? Carbon::now()->addHours(24)
                : Carbon::now()->addDays(7);

            $owner->fdeletionwasinactive = $owner->dcompanynonactive !== null ? 1 : 0;
            $owner->dcompanynonactive = $owner->dcompanynonactive ?? Carbon::now();
            $owner->ddeletionrequested = Carbon::now();
            $owner->ddeleteafter = $gracePeriod;
            $owner->save();
        });

        // Send Email & Log
        $logStatus = 'sent';
        $logError = null;

        try {
            Mail::to($owner->cemail)->send(new CompanyDeletionRequestedMail($owner));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email CompanyDeletionRequestedMail: ' . $e->getMessage());
            $logStatus = 'failed';
            $logError = $e->getMessage();
        }

        TaccountLifecycleEmailLog::create([
            'nid_owner_snapshot'   => $owner->nid,
            'cevent'               => 'deletion_requested',
            'ccompany_snapshot'    => $owner->cperusahaan,
            'cowner_name_snapshot' => $owner->cnamalengkap,
            'cowner_email_snapshot' => $owner->cemail,
            'cstatus'              => $logStatus,
            'cerror'               => $logError,
            'dsent'                => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Penghapusan perusahaan berhasil dijadwalkan.',
            'data'    => $owner->getCompanyLifecycleState(),
        ]);
    }

    /**
     * 5. Cancel Company Deletion (Owner Only)
     */
    public function cancelDeletion(Request $request)
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
                'message' => 'Hanya Owner perusahaan yang dapat membatalkan penghapusan perusahaan.',
            ], 403);
        }

        if (!$user->isDeletionPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Perusahaan tidak dalam proses penghapusan.',
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

        // DB Transaction
        $owner = null;
        $isInactive = false;

        DB::transaction(function () use ($user, &$owner, &$isInactive) {
            $owner = Muser::where('nid', $user->nid)->lockForUpdate()->first();
            $isInactive = (bool) $owner->fdeletionwasinactive;

            $owner->ddeletionrequested = null;
            $owner->ddeleteafter = null;

            if (!$isInactive) {
                $owner->dcompanynonactive = null;
            }

            $owner->fdeletionwasinactive = 0;
            $owner->save();
        });

        // Send Email & Log
        $logStatus = 'sent';
        $logError = null;

        try {
            Mail::to($owner->cemail)->send(new CompanyDeletionCancelledMail($owner, $isInactive));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email CompanyDeletionCancelledMail: ' . $e->getMessage());
            $logStatus = 'failed';
            $logError = $e->getMessage();
        }

        TaccountLifecycleEmailLog::create([
            'nid_owner_snapshot'   => $owner->nid,
            'cevent'               => 'deletion_cancelled',
            'ccompany_snapshot'    => $owner->cperusahaan,
            'cowner_name_snapshot' => $owner->cnamalengkap,
            'cowner_email_snapshot' => $owner->cemail,
            'cstatus'              => $logStatus,
            'cerror'               => $logError,
            'dsent'                => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Penghapusan perusahaan berhasil dibatalkan.',
            'data'    => $owner->getCompanyLifecycleState(),
        ]);
    }
}
