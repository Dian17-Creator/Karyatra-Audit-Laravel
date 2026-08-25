<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Audit\Maudit;
use App\Models\Audit\MkatTanya;
use App\Models\Audit\Mtanya;
use App\Models\Stock\MkatBarang;
use App\Models\Stock\Mbarang;
use App\Models\Stock\Mopname;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        // Audit Stats
        $katQuery = MkatTanya::query();
        $tanyaQuery = Mtanya::query();
        $auditQuery = Maudit::query();

        if ($company) {
            $katQuery->where('cperusahaan', $company);
            $tanyaQuery->whereHas('category', function ($q) use ($company) {
                $q->where('cperusahaan', $company);
            });
            $auditQuery->whereHas('department', function ($q) use ($company) {
                $q->where('cperusahaan', $company);
            });
        }

        $totalKategori = $katQuery->count();
        $totalPertanyaan = $tanyaQuery->count();
        $totalAudit = $auditQuery->count();

        // Stock Stats
        $stockKatQuery = MkatBarang::query();
        $barangQuery = Mbarang::query();
        $opnameQuery = Mopname::query();

        if ($company) {
            $stockKatQuery->where('cperusahaan', $company);
            $barangQuery->whereHas('group', function ($q) use ($company) {
                $q->where('cperusahaan', $company);
            });
            $opnameQuery->whereHas('department', function ($q) use ($company) {
                $q->where('cperusahaan', $company);
            });
        }

        $totalKategoriStok = $stockKatQuery->count();
        $totalBarang = $barangQuery->count();
        $totalStokOpname = $opnameQuery->count();

        // Recent Audits
        $recentAuditQuery = Maudit::with('department')->orderBy('nid', 'desc');
        if ($company) {
            $recentAuditQuery->whereHas('department', function ($q) use ($company) {
                $q->where('cperusahaan', $company);
            });
        }
        $recentAudits = $recentAuditQuery->take(5)->get();

        $recentActivity = $recentAudits->map(function ($audit) {
            $dateStr = '-';
            if ($audit->daudit) {
                $dateStr = is_string($audit->daudit) ? date('d M Y', strtotime($audit->daudit)) : $audit->daudit->format('d M Y');
            }
            return [
                'id'       => $audit->nid,
                'title'    => 'Audit ' . ($audit->department->cnama ?? 'Departemen'),
                'subtitle' => ($audit->cdocid ?? '-') . ' • ' . $dateStr,
                'status'   => $audit->cstatus ?? 'Draft'
            ];
        });

        // Recent Stock Opname
        $recentOpnameQuery = Mopname::with('department')->orderBy('nid', 'desc');
        if ($company) {
            $recentOpnameQuery->whereHas('department', function ($q) use ($company) {
                $q->where('cperusahaan', $company);
            });
        }
        $recentOpnames = $recentOpnameQuery->take(5)->get();

        $recentStockOpname = $recentOpnames->map(function ($opname) {
            $dateStr = '-';
            if ($opname->daudit) {
                $dateStr = is_string($opname->daudit) ? date('d M Y', strtotime($opname->daudit)) : $opname->daudit->format('d M Y');
            }
            return [
                'id'       => $opname->nid,
                'title'    => 'Stok Opname ' . ($opname->department->cnama ?? 'Departemen'),
                'subtitle' => ($opname->cdocid ?? '-') . ' • ' . $dateStr,
                'status'   => $opname->cstatus ?? 'Draft'
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Dashboard summary berhasil diambil.',
            'data'    => [
                'total_kategori'      => $totalKategori,
                'total_pertanyaan'    => $totalPertanyaan,
                'total_audit'         => $totalAudit,
                'total_kategori_stok' => $totalKategoriStok,
                'total_barang'        => $totalBarang,
                'total_stok_opname'   => $totalStokOpname,
                'recent_activity'     => $recentActivity,
                'recent_stock_opname' => $recentStockOpname
            ]
        ]);
    }
}
