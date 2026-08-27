<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auth\Muser;
use App\Models\Subscription\MsubscriptionPlan;
use App\Models\Subscription\Tsubscription;
use App\Services\SubscriptionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * GET /api/subscription/state
     * Mengembalikan status entitlement berlangganan perusahaan
     */
    public function state(Request $request)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terotentikasi.'
                ], 401);
            }

            $state = $this->subscriptionService->getSubscriptionState($user);

            return response()->json([
                'success' => true,
                'message' => 'Status langganan berhasil diambil.',
                'data'    => $state
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil status langganan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/subscription/plans
     * Menampilkan daftar paket berlangganan aktif
     */
    public function plans()
    {
        try {
            $plans = MsubscriptionPlan::enabled()
                ->orderBy('nsort', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar paket berlangganan berhasil diambil.',
                'data'    => $plans
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar paket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/subscription/request
     * Pengajuan berlangganan oleh Owner perusahaan
     */
    public function request(Request $request)
    {
        $request->validate([
            'plan_id'       => 'required|integer|exists:msubscription_plan,nid',
            'amount'        => 'required|numeric|min:0',
            'payment_proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240', // Max 10MB
            'payment_ref'   => 'nullable|string|max:255',
        ]);

        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terotentikasi.'
                ], 401);
            }

            // Validasi: Hanya untuk Owner dan email owner terverifikasi
            if (!$user->isOwner()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya Owner perusahaan yang berhak mengajukan berlangganan.'
                ], 403);
            }

            if (!$user->isEmailVerified()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Silakan lakukan verifikasi email terlebih dahulu sebelum mengajukan berlangganan.'
                ], 400);
            }

            $plan = MsubscriptionPlan::findOrFail($request->plan_id);

            if (!$plan->fenabled || $plan->nprice === null || $plan->nprice <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paket berlangganan yang dipilih tidak aktif.'
                ], 400);
            }

            // Mismatch Price Check
            if (abs((float) $request->amount - (float) $plan->nprice) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'Harga paket tidak sesuai dengan katalog resmi. Silakan refresh halaman.'
                ], 400);
            }

            return DB::transaction(function () use ($request, $user, $plan) {
                // Lock user owner record
                Muser::where('nid', $user->nid)->lockForUpdate()->first();

                // Cek apakah sudah ada pengajuan pending
                $hasPending = Tsubscription::where('nid_owner', $user->nid)
                    ->where('cstatus', 'pending')
                    ->lockForUpdate()
                    ->exists();

                if ($hasPending) {
                    throw new Exception("Anda sudah memiliki pengajuan langganan yang sedang diproses (pending).");
                }

                // Simpan berkas ke storage/app/private/subscription_proofs/{company_hash}/owner_{id}/
                $companyHash = md5($user->cperusahaan);
                $relativeDir = "subscription_proofs/{$companyHash}/owner_{$user->nid}";

                $file = $request->file('payment_proof');
                $filename = 'proof_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $storedPath = $file->storeAs($relativeDir, $filename, 'local');

                $subscription = Tsubscription::create([
                    'nid_owner'        => $user->nid,
                    'nid_plan'         => $plan->nid,
                    'cplan_name'       => $plan->cnama,
                    'nduration_months' => $plan->nduration_months,
                    'namount'          => $plan->nprice,
                    'cstatus'          => 'pending',
                    'cpayment_proof'   => $storedPath,
                    'cpayment_ref'     => $request->payment_ref ?? null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Pengajuan berlangganan berhasil dikirim. Menunggu verifikasi tim finance.',
                    'data'    => $subscription
                ], 201);
            });
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
