<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MauditKat;
use App\Models\MauditQuest;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $totalKategori = MauditKat::count();

        $totalPertanyaan = MauditQuest::count();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard summary berhasil diambil.',
            'data' => [
                'total_kategori'   => $totalKategori,
                'total_pertanyaan' => $totalPertanyaan,
            ]
        ]);
    }
}
