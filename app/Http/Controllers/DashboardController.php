<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Audit\Maudit;
use App\Models\Audit\MkatTanya;
use App\Models\Audit\Mtanya;
use App\Models\Stock\MauditItemgrp;
use App\Models\Stock\MauditItem;
use App\Models\Stock\MauditInventory;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        // Audit Stats
        $totalKategori = MkatTanya::count();
        $totalPertanyaan = Mtanya::count();
        $totalAudit = Maudit::count();

        // Stock Stats
        // $totalKategoriStok = MauditItemgrp::count();
        // $totalBarang = MauditItem::count();
        // $totalStokOpname = MauditInventory::count();

        $recentAudits = Maudit::with('department')
            ->orderBy('nid', 'desc')
            ->take(5)
            ->get();

        $recentActivity = $recentAudits->map(function ($audit) {
            $dateStr = '-';
            if ($audit->daudit) {
                $dateStr = is_string($audit->daudit) ? date('d M Y', strtotime($audit->daudit)) : $audit->daudit->format('d M Y');
            }
            return [
                'id' => $audit->nid,
                'title' => 'Audit ' . ($audit->department->cnama ?? 'Departemen'),
                'subtitle' => ($audit->cdocid ?? '-') . ' • ' . $dateStr,
                'status' => $audit->cstatus ?? 'Draft'
            ];
        });

        // Recent Stock Opname
        // $recentOpnames = MauditInventory::with('department')
        //     ->orderBy('nid', 'desc')
        //     ->take(5)
        //     ->get();

        // $recentStockOpname = $recentOpnames->map(function ($opname) {
        //     $dateStr = '-';
        //     if ($opname->daudit) {
        //         $dateStr = is_string($opname->daudit) ? date('d M Y', strtotime($opname->daudit)) : $opname->daudit->format('d M Y');
        //     }
        //     return [
        //         'id' => $opname->nid,
        //         'title' => 'Stok Opname ' . ($opname->department->cnama ?? 'Departemen'),
        //         'subtitle' => ($opname->cdocid ?? '-') . ' • ' . $dateStr,
        //         'status' => $opname->cstatus ?? 'Draft'
        //     ];
        // });

        return response()->json([
            'success' => true,
            'message' => 'Dashboard summary berhasil diambil.',
            'data' => [
                'total_kategori'   => $totalKategori,
                'total_pertanyaan' => $totalPertanyaan,
                'total_audit'      => $totalAudit,
                // 'total_kategori_stok' => $totalKategoriStok,
                // 'total_barang'     => $totalBarang,
                // 'total_stok_opname' => $totalStokOpname,
                'recent_activity'  => $recentActivity,
                // 'recent_stock_opname' => $recentStockOpname
            ]
        ]);
    }
}
