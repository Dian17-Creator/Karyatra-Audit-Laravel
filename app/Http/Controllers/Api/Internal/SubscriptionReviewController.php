<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\Subscription\Tsubscription;
use App\Mail\SubscriptionDecisionMail;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SubscriptionReviewController extends Controller
{
    /**
     * POST /api/internal/subscription/review
     * Verifikasi persetujuan / penolakan transaksi berlangganan oleh Finance Backoffice
     */
    public function review(Request $request)
    {
        $request->validate([
            'subscription_id' => 'required|integer|exists:tsubscription,nid',
            'action'          => 'required|string|in:approve,reject',
            'note'            => 'nullable|string|max:1000',
            'reviewer_id'     => 'nullable|integer',
        ]);

        try {
            $reviewerId = $request->reviewer_id ?? (Auth::id() ?? 1);

            $subscription = DB::transaction(function () use ($request, $reviewerId) {
                $sub = Tsubscription::with(['owner', 'plan'])->where('nid', $request->subscription_id)->lockForUpdate()->firstOrFail();

                if ($sub->cstatus !== 'pending') {
                    throw new Exception("Transaksi berlangganan ini sudah diproses sebelumnya (Status: {$sub->cstatus}).");
                }

                $now = Carbon::now();

                if ($request->action === 'approve') {
                    // Cari jika sudah ada paket Pro aktif atau terjadwal untuk owner ini
                    $activeLatestEnd = Tsubscription::where('nid_owner', $sub->nid_owner)
                        ->where('cstatus', 'approved')
                        ->whereNotNull('dend')
                        ->where('dend', '>', $now)
                        ->max('dend');

                    if ($activeLatestEnd) {
                        $dstart = Carbon::parse($activeLatestEnd);
                    } else {
                        $dstart = $now;
                    }

                    $dend = (clone $dstart)->addMonths($sub->nduration_months);

                    $sub->cstatus = 'approved';
                    $sub->dstart  = $dstart;
                    $sub->dend    = $dend;
                    $sub->cnote   = $request->note ?? 'Persetujuan langganan berhasil.';
                } else {
                    $sub->cstatus = 'rejected';
                    $sub->cnote   = $request->note ?? 'Pengajuan langganan ditolak.';
                }

                $sub->nid_reviewed_by = $reviewerId;
                $sub->dreviewed        = $now;
                $sub->save();

                // Kirim Email Keputusan setelah transaksi DB berhasil di-commit
                DB::afterCommit(function () use ($sub) {
                    $this->sendDecisionEmail($sub);
                });

                return $sub;
            });

            return response()->json([
                'success' => true,
                'message' => 'Keputusan verifikasi berlangganan berhasil diproses.',
                'data'    => $subscription
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses verifikasi: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Helper kirim email keputusan ke owner dan update status email
     */
    protected function sendDecisionEmail(Tsubscription $sub): void
    {
        try {
            $ownerEmail = $sub->owner ? $sub->owner->cemail : null;
            if (!$ownerEmail) {
                Log::warning("SubscriptionReviewController: Email owner tidak ditemukan untuk sub ID #{$sub->nid}");
                $sub->cdecision_email_status = 'failed';
                $sub->save();
                return;
            }

            Mail::to($ownerEmail)->send(new SubscriptionDecisionMail($sub));

            $sub->cdecision_email_status = 'sent';
            $sub->ddecision_email_sent   = Carbon::now();
            $sub->save();
        } catch (Exception $e) {
            Log::error("SubscriptionReviewController: Gagal mengirim email keputusan sub ID #{$sub->nid}: " . $e->getMessage());
            $sub->cdecision_email_status = 'failed';
            $sub->save();
        }
    }
}
