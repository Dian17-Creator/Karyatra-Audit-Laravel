<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\Subscription\MsubscriptionPlan;
use Exception;
use Illuminate\Http\Request;

class SubscriptionPlanManageController extends Controller
{
    /**
     * GET /api/internal/subscription/plans
     * List seluruh paket berlangganan (aktif & non-aktif)
     */
    public function index()
    {
        try {
            $plans = MsubscriptionPlan::orderBy('nsort', 'asc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Semua katalog paket berlangganan berhasil diambil.',
                'data'    => $plans
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data paket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/internal/subscription/plans
     * Tambah paket berlangganan baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'ccode'            => 'required|string|max:50|unique:msubscription_plan,ccode',
            'cnama'            => 'required|string|max:100',
            'nduration_months' => 'required|integer|min:1',
            'nprice'           => 'required|numeric|min:0',
            'nreference_price' => 'nullable|numeric|min:0',
            'cdescription'     => 'nullable|string',
            'cbadge'           => 'nullable|string|max:100',
            'fenabled'         => 'nullable|boolean',
            'nsort'            => 'nullable|integer',
        ]);

        try {
            $plan = MsubscriptionPlan::create([
                'ccode'            => $request->ccode,
                'cnama'            => $request->cnama,
                'nduration_months' => $request->nduration_months,
                'nprice'           => $request->nprice,
                'nreference_price' => $request->nreference_price,
                'cdescription'     => $request->cdescription,
                'cbadge'           => $request->cbadge,
                'fenabled'         => $request->has('fenabled') ? (bool) $request->fenabled : true,
                'nsort'            => $request->nsort ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paket berlangganan baru berhasil dibuat.',
                'data'    => $plan
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat paket: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * PUT /api/internal/subscription/plans/{id}
     * Update rincian paket berlangganan
     */
    public function update(Request $request, int $id)
    {
        $plan = MsubscriptionPlan::findOrFail($id);

        $request->validate([
            'ccode'            => 'required|string|max:50|unique:msubscription_plan,ccode,' . $id . ',nid',
            'cnama'            => 'required|string|max:100',
            'nduration_months' => 'required|integer|min:1',
            'nprice'           => 'required|numeric|min:0',
            'nreference_price' => 'nullable|numeric|min:0',
            'cdescription'     => 'nullable|string',
            'cbadge'           => 'nullable|string|max:100',
            'fenabled'         => 'nullable|boolean',
            'nsort'            => 'nullable|integer',
        ]);

        try {
            $plan->update([
                'ccode'            => $request->ccode,
                'cnama'            => $request->cnama,
                'nduration_months' => $request->nduration_months,
                'nprice'           => $request->nprice,
                'nreference_price' => $request->nreference_price,
                'cdescription'     => $request->cdescription,
                'cbadge'           => $request->cbadge,
                'fenabled'         => $request->has('fenabled') ? (bool) $request->fenabled : $plan->fenabled,
                'nsort'            => $request->nsort ?? $plan->nsort,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paket berlangganan berhasil diperbarui.',
                'data'    => $plan
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui paket: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * PATCH /api/internal/subscription/plans/{id}/toggle
     * Toggle status aktif/non-aktif paket
     */
    public function toggle(int $id)
    {
        try {
            $plan = MsubscriptionPlan::findOrFail($id);
            $plan->fenabled = !$plan->fenabled;
            $plan->save();

            $statusStr = $plan->fenabled ? 'diaktifkan' : 'dinonaktifkan';

            return response()->json([
                'success' => true,
                'message' => "Paket berlangganan berhasil {$statusStr}.",
                'data'    => $plan
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status paket: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * DELETE /api/internal/subscription/plans/{id}
     * Hapus paket jika belum pernah digunakan di transaksi
     */
    public function destroy(int $id)
    {
        try {
            $plan = MsubscriptionPlan::withCount('subscriptions')->findOrFail($id);

            if ($plan->subscriptions_count > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paket tidak dapat dihapus karena sudah memiliki histori transaksi.'
                ], 400);
            }

            $plan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Paket berlangganan berhasil dihapus.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus paket: ' . $e->getMessage()
            ], 400);
        }
    }
}
