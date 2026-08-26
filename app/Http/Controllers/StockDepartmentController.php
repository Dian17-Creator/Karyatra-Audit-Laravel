<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

use App\Models\Auth\Mdepartemen;
use App\Models\Stock\Mbarang;
use App\Models\Stock\MkatBarang;
use App\Models\Stock\TdeptBarang;
use App\Models\Stock\Mopname;

class StockDepartmentController extends Controller
{
    /**
     * Get all departments.
     *
     * GET /api/stock/departments
     */
    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $query = Mdepartemen::select(
            'nid as id',
            'cnama as name'
        );

        if ($company) {
            $query->where('cperusahaan', $company);
        }

        $departments = $query->orderBy('cnama')->get();

        return response()->json([
            'success' => true,
            'message' => 'Data department berhasil diambil.',
            'data'    => $departments,
        ]);
    }

    /**
     * Get department to stock items mapping.
     *
     * GET /api/stock/departments/{id}/mapping
     */
    public function mapping(Request $request, $id): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $query = Mdepartemen::where('nid', $id);
        if ($company) {
            $query->where('cperusahaan', $company);
        }

        $department = $query->first();

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department tidak ditemukan.',
            ], 404);
        }

        /*
         * Ambil ID barang yang sudah terhubung
         * dengan department tersebut.
         */
        $linkedItemIds = TdeptBarang::where(
            'nid_dept',
            $department->nid
        )
            ->pluck('nid_barang')
            ->toArray();

        /*
         * Ambil semua kelompok/category barang milik perusahaan.
         */
        $catQuery = MkatBarang::orderBy('cnama');
        if ($company) {
            $catQuery->where('cperusahaan', $company);
        }
        $categories = $catQuery->get();
        $categoryIds = $categories->pluck('nid')->toArray();

        /*
         * Ambil semua barang dari kategori perusahaan.
         */
        $items = Mbarang::whereIn('nid_kat', $categoryIds)
            ->orderBy('nurut')
            ->orderBy('cbarang')
            ->get();

        /*
         * Kelompokkan barang berdasarkan nid_kat.
         */
        $groupedItems = $items->groupBy('nid_kat');

        $formattedCategories = [];

        foreach ($categories as $category) {
            $categoryItems = $groupedItems->get(
                $category->nid,
                collect()
            );

            $formattedItems = $categoryItems->map(function ($item) use ($linkedItemIds) {
                return [
                    'id'       => $item->nid,
                    'name'     => $item->cbarang,
                    'sequence' => $item->nurut,
                    'linked'   => in_array(
                        $item->nid,
                        $linkedItemIds
                    ),
                ];
            })->values();

            $formattedCategories[] = [
                'id'    => $category->nid,
                'name'  => $category->cnama,
                'items' => $formattedItems,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Data mapping barang berhasil diambil.',
            'data'    => [
                'department' => [
                    'id'   => $department->nid,
                    'name' => $department->cnama,
                ],
                'categories' => $formattedCategories,
            ],
        ]);
    }

    /**
     * Store / update department to stock items mapping.
     *
     * POST /api/stock/departments/mapping
     */
    public function storeMapping(Request $request): JsonResponse
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        $validator = Validator::make($request->all(), [
            'department_id' => 'required|integer|exists:mdepartemen,nid',
            'item_ids'      => 'nullable|array',
            'item_ids.*'    => [
                'integer',
                'exists:mbarang,nid',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $company = $this->resolveCompany($request);
        $departmentId = (int) $request->department_id;

        $deptQuery = Mdepartemen::where('nid', $departmentId);
        if ($company) {
            $deptQuery->where('cperusahaan', $company);
        }
        $department = $deptQuery->first();

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department tidak ditemukan.',
            ], 404);
        }

        /*
         * Hilangkan duplicate item ID.
         */
        $itemIds = collect($request->input('item_ids', []))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        /*
         * CEK AUDIT INVENTORY AKTIF
         */
        $activeAudit = Mopname::where(
            'nid_dept',
            $departmentId
        )
            ->whereIn('cstatus', [
                'Draft',
                'In Progress',
            ])
            ->first();

        if ($activeAudit) {
            return response()->json([
                'success' => false,
                'message' => 'Pemetaan barang tidak dapat diubah karena department sedang digunakan dalam audit inventory.',
                'data'    => [
                    'document_id' => $activeAudit->cdocid,
                    'status'      => $activeAudit->cstatus,
                ],
            ], 409);
        }

        DB::beginTransaction();

        try {
            /*
             * Hapus seluruh mapping lama department.
             */
            TdeptBarang::where(
                'nid_dept',
                $departmentId
            )->delete();

            /*
             * Masukkan mapping baru.
             */
            if (!empty($itemIds)) {
                $mappingData = [];

                foreach ($itemIds as $itemId) {
                    $mappingData[] = [
                        'nid_dept'   => $departmentId,
                        'nid_barang' => $itemId,
                    ];
                }

                TdeptBarang::insert($mappingData);
            }

            DB::commit();

            /*
             * Ambil kembali mapping setelah berhasil disimpan.
             */
            $savedItemIds = TdeptBarang::where(
                'nid_dept',
                $departmentId
            )
                ->pluck('nid_barang')
                ->map(fn($id) => (int) $id)
                ->values()
                ->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Pemetaan barang berhasil disimpan.',
                'data'    => [
                    'department' => [
                        'id'   => $department->nid,
                        'name' => $department->cnama,
                    ],
                    'item_ids'    => $savedItemIds,
                    'total_items' => count($savedItemIds),
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error(
                'Gagal menyimpan pemetaan barang department.',
                [
                    'department_id' => $departmentId,
                    'item_ids'      => $itemIds,
                    'error'         => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan pemetaan barang.',
            ], 500);
        }
    }
}
