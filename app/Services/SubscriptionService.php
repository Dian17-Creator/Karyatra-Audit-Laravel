<?php

namespace App\Services;

use App\Models\Auth\Muser;
use App\Models\Audit\TauditFoto;
use App\Models\Stock\TopnameFoto;
use App\Models\Subscription\Tsubscription;
use Carbon\Carbon;

class SubscriptionService
{
    /**
     * Resolusi owner perusahaan dari $user->cperusahaan di mana fowner = 1 dan dnonactive IS NULL
     */
    public function getCompanyOwner(Muser $user): Muser
    {
        if ($user->isOwner() && $user->isActive()) {
            return $user;
        }

        $owner = Muser::where('cperusahaan', $user->cperusahaan)
            ->where('fowner', 1)
            ->whereNull('dnonactive')
            ->first();

        return $owner ?? $user;
    }

    /**
     * Mengembalikan status entitlement berlangganan perusahaan
     */
    public function getSubscriptionState(Muser $user): array
    {
        $owner = $this->getCompanyOwner($user);
        $now = Carbon::now();

        $isOwnerVerified = $owner->isEmailVerified();

        // 1. Trial Check: Email owner belum terverifikasi
        if (!$isOwnerVerified) {
            $upgradePending = Tsubscription::where('nid_owner', $owner->nid)
                ->where('cstatus', 'pending')
                ->exists();

            $latestSub = Tsubscription::where('nid_owner', $owner->nid)
                ->orderBy('nid', 'desc')
                ->first();

            return [
                'plan'              => 'trial',
                'pro_start'         => null,
                'pro_current_until' => null,
                'pro_until'         => null,
                'upgrade_pending'   => $upgradePending,
                'rejection_visible' => $latestSub ? ($latestSub->cstatus === 'rejected') : false,
                'owner_verified'    => false,
            ];
        }

        // 2. Fetch semua approved subscriptions untuk owner, urutkan berdasarkan dstart
        $approvedSubs = Tsubscription::where('nid_owner', $owner->nid)
            ->where('cstatus', 'approved')
            ->whereNotNull('dstart')
            ->whereNotNull('dend')
            ->orderBy('dstart', 'asc')
            ->get();

        // Cari active approved sub (dstart <= now & dend > now)
        $activeSub = $approvedSubs->first(function ($sub) use ($now) {
            return $sub->dstart <= $now && $sub->dend > $now;
        });

        $plan = 'free';
        $proStart = null;
        $proCurrentUntil = null;
        $proUntil = null;

        if ($activeSub) {
            $plan = 'pro';
            $proStart = $activeSub->dstart->toIso8601String();
            $proCurrentUntil = $activeSub->dend->toIso8601String();

            // Hitung continuous extension tanpa gap
            $currentEnd = $activeSub->dend;
            foreach ($approvedSubs as $sub) {
                if ($sub->dstart > $activeSub->dstart) {
                    // Jika tanggal mulai perpanjangan <= batas akhir saat ini (atau gap <= 1 hari), perpanjang chain
                    if ($sub->dstart->lte($currentEnd->copy()->addDay())) {
                        if ($sub->dend->gt($currentEnd)) {
                            $currentEnd = $sub->dend;
                        }
                    }
                }
            }
            $proUntil = $currentEnd->toIso8601String();
        }

        // Indicator upgrade_pending & rejection_visible
        $upgradePending = Tsubscription::where('nid_owner', $owner->nid)
            ->where('cstatus', 'pending')
            ->exists();

        $latestSub = Tsubscription::where('nid_owner', $owner->nid)
            ->orderBy('nid', 'desc')
            ->first();

        $rejectionVisible = $latestSub ? ($latestSub->cstatus === 'rejected') : false;

        return [
            'plan'              => $plan,
            'pro_start'         => $proStart,
            'pro_current_until' => $proCurrentUntil,
            'pro_until'         => $proUntil,
            'upgrade_pending'   => $upgradePending,
            'rejection_visible' => $rejectionVisible,
            'owner_verified'    => true,
        ];
    }

    /**
     * Cek apakah perusahaan memiliki paket Pro yang aktif
     */
    public function isPro(Muser $user): bool
    {
        $state = $this->getSubscriptionState($user);
        return $state['plan'] === 'pro';
    }

    /**
     * Kebijakan batas upload foto bukti temuan (Audit) / opname (Stock)
     */
    public function getEvidencePhotoUploadPolicy(Muser $user, string $type, int $resultId): array
    {
        $state = $this->getSubscriptionState($user);
        $plan = $state['plan'];

        // Count existing photos
        $currentPhotos = 0;
        if ($type === 'audit') {
            $currentPhotos = TauditFoto::where('nid_hasil', $resultId)->count();
        } elseif ($type === 'opname') {
            $currentPhotos = TopnameFoto::where('nid_hasil', $resultId)->count();
        }

        // Aturan 1: Trial -> Maksimal 1 foto per pertanyaan Audit / barang Opname
        if ($plan === 'trial') {
            $maxPhotos = 1;
            if ($currentPhotos >= $maxPhotos) {
                return [
                    'allowed'        => false,
                    'max_photos'     => $maxPhotos,
                    'current_photos' => $currentPhotos,
                    'reason'         => 'Batas maksimal 1 foto bukti pada masa Trial sudah tercapai.',
                ];
            }
            return [
                'allowed'        => true,
                'max_photos'     => $maxPhotos,
                'current_photos' => $currentPhotos,
                'reason'         => null,
            ];
        }

        // Aturan 2: Free / Pending -> Upload tidak diperbolehkan (allowed = false)
        if ($plan === 'free') {
            return [
                'allowed'        => false,
                'max_photos'     => 0,
                'current_photos' => $currentPhotos,
                'reason'         => 'Paket Free tidak diizinkan mengunggah foto bukti. Silakan upgrade ke paket Pro.',
            ];
        }

        // Aturan 3: Pro -> Diizinkan (Audit max 10, Opname max 5)
        $maxPhotos = ($type === 'audit') ? 10 : 5;
        if ($currentPhotos >= $maxPhotos) {
            return [
                'allowed'        => false,
                'max_photos'     => $maxPhotos,
                'current_photos' => $currentPhotos,
                'reason'         => "Maksimal {$maxPhotos} foto untuk " . ($type === 'audit' ? 'Audit' : 'Stok Opname') . " pada paket Pro sudah tercapai.",
            ];
        }

        return [
            'allowed'        => true,
            'max_photos'     => $maxPhotos,
            'current_photos' => $currentPhotos,
            'reason'         => null,
        ];
    }
}
