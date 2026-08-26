<?php

namespace App\Http\Controllers;

use App\Models\Stock\MkatBarang;
use App\Models\Stock\Mbarang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class StockCategoryController extends Controller
{
    /**
     * GET /api/stock/categories
     */
    public function index(Request $request)
    {
        try {
            $company = $this->resolveCompany($request);

            $query = MkatBarang::with(['items' => function ($q) {
                $q->orderBy('nurut');
            }]);

            if ($company) {
                $query->where('cperusahaan', $company);
            }

            $categories = $query->orderBy('nid')
                ->get()
                ->map(function ($cat) {
                    return [
                        'id'          => $cat->nid,
                        'name'        => $cat->cnama,
                        'description' => $cat->cket,
                        'items'       => $cat->items->map(function ($item) {
                            return [
                                'id'          => $item->nid,
                                'category_id' => $item->nid_kat,
                                'name'        => $item->cbarang,
                                'sequence'    => $item->nurut,
                            ];
                        }),
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Data pohon kategori dan barang berhasil diambil.',
                'data'    => $categories,
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal mengambil pohon kategori dan barang: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
            ], 500);
        }
    }

    /**
     * POST /api/stock/categories
     * Tambah Kategori Baru
     */
    public function storeCategory(Request $request)
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        if (empty($request->cnama)) {
            return response()->json([
                'success' => false,
                'message' => 'Nama kategori harus diisi.'
            ], 400);
        }

        try {
            $company = $this->resolveCompany($request);

            if (empty($company)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi perusahaan tidak valid. Pengguna/perusahaan tidak ditemukan.'
                ], 422);
            }

            $category = MkatBarang::create([
                'cnama'       => $request->cnama,
                'cket'        => $request->cket,
                'cperusahaan' => $company,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kategori berhasil ditambahkan.',
                'data'    => [
                    'id'          => $category->nid,
                    'name'        => $category->cnama,
                    'description' => $category->cket,
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Gagal menambahkan kategori: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan kategori.',
            ], 500);
        }
    }

    /**
     * PUT /api/stock/categories/{id?}
     * Edit Kategori
     */
    public function updateCategory(Request $request, $id = null)
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        $nid = $id ?? $request->nid;
        $cnama = $request->cnama;

        if (empty($nid) || empty($cnama)) {
            return response()->json([
                'success' => false,
                'message' => 'ID kategori dan Nama kategori harus diisi.'
            ], 400);
        }

        try {
            $company = $this->resolveCompany($request);

            $query = MkatBarang::where('nid', $nid);
            if ($company) {
                $query->where('cperusahaan', $company);
            }

            $category = $query->first();

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan.'
                ], 404);
            }

            $category->update([
                'cnama' => $cnama,
                'cket'  => $request->cket,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kategori berhasil diperbarui.',
                'data'    => [
                    'id'          => $category->nid,
                    'name'        => $category->cnama,
                    'description' => $category->cket,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal memperbarui kategori: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui kategori.',
            ], 500);
        }
    }

    /**
     * DELETE /api/stock/categories/{id?}
     * Hapus Kategori
     */
    public function destroyCategory(Request $request, $id = null)
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        $nid = $id ?? $request->nid;

        if (empty($nid)) {
            return response()->json([
                'success' => false,
                'message' => 'ID kategori harus diisi.'
            ], 400);
        }

        try {
            $company = $this->resolveCompany($request);

            $query = MkatBarang::where('nid', $nid);
            if ($company) {
                $query->where('cperusahaan', $company);
            }

            $category = $query->first();

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan.'
                ], 404);
            }

            // Validasi Transaksi: pastikan tidak ada barang di dalam kategori ini
            // yang sudah pernah digunakan dalam transaksi opname (topname_hasil)
            $isUsed = DB::table('mbarang')
                ->join('topname_hasil', 'topname_hasil.nid_barang', '=', 'mbarang.nid')
                ->where('mbarang.nid_kat', $category->nid)
                ->exists();

            if ($isUsed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak dapat dihapus karena satu atau lebih barang sudah digunakan dalam proses opname.'
                ], 409);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Kategori berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal menghapus kategori: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus kategori.',
            ], 500);
        }
    }

    /**
     * GET /api/stock/categories/{categoryId}/items
     * Mengambil semua barang berdasarkan kategori
     */
    public function getItems(Request $request, $categoryId)
    {
        try {
            $company = $this->resolveCompany($request);

            $query = MkatBarang::where('nid', $categoryId);
            if ($company) {
                $query->where('cperusahaan', $company);
            }

            $category = $query->first();

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan.',
                ], 404);
            }

            $items = Mbarang::where('nid_kat', $category->nid)
                ->orderBy('nurut')
                ->orderBy('nid')
                ->get()
                ->map(function ($item) {
                    return [
                        'id'          => $item->nid,
                        'category_id' => $item->nid_kat,
                        'name'        => $item->cbarang,
                        'sequence'    => $item->nurut,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Data barang berhasil diambil.',
                'data'    => [
                    'category' => [
                        'id'          => $category->nid,
                        'name'        => $category->cnama,
                        'description' => $category->cket,
                    ],
                    'items'    => $items,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error(
                'Gagal mengambil barang berdasarkan kategori: ' . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data barang.',
            ], 500);
        }
    }

    /**
     * POST /api/stock/items
     * Tambah Barang
     */
    public function storeItem(Request $request)
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        $nid_kat = $request->nid_kat ?? $request->nid_grp ?? $request->category_id;
        $cbarang = $request->cbarang ?? $request->citemname ?? $request->name;

        if (empty($nid_kat) || empty($cbarang)) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori dan Nama barang harus diisi.'
            ], 400);
        }

        try {
            $company = $this->resolveCompany($request);

            $query = MkatBarang::where('nid', $nid_kat);
            if ($company) {
                $query->where('cperusahaan', $company);
            }

            $categoryExists = $query->exists();
            if (!$categoryExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan.'
                ], 404);
            }

            DB::beginTransaction();

            $maxSequence = Mbarang::where('nid_kat', $nid_kat)->max('nurut') ?? 0;
            $nurut = $maxSequence + 1;

            $item = Mbarang::create([
                'nid_kat' => $nid_kat,
                'cbarang' => $cbarang,
                'nurut'   => $nurut,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil ditambahkan.',
                'data'    => [
                    'id'          => $item->nid,
                    'category_id' => $item->nid_kat,
                    'name'        => $item->cbarang,
                    'sequence'    => $item->nurut,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal menambahkan barang: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan barang.',
            ], 500);
        }
    }

    /**
     * DELETE /api/stock/items/{id?}
     * Hapus Barang
     */
    public function destroyItem(Request $request, $id = null)
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        $nid = $id ?? $request->nid;

        if (empty($nid)) {
            return response()->json([
                'success' => false,
                'message' => 'ID barang harus diisi.'
            ], 400);
        }

        try {
            $company = $this->resolveCompany($request);

            $item = Mbarang::find($nid);

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barang tidak ditemukan.'
                ], 404);
            }

            // Validasi kepemilikan kategori barang oleh perusahaan
            if ($company) {
                $categoryOwned = MkatBarang::where('nid', $item->nid_kat)
                    ->where('cperusahaan', $company)
                    ->exists();

                if (!$categoryOwned) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Barang tidak ditemukan.'
                    ], 404);
                }
            }

            // Validasi Transaksi: pastikan barang belum pernah digunakan dalam opname
            $isUsed = DB::table('topname_hasil')->where('nid_barang', $nid)->exists();

            if ($isUsed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barang tidak dapat dihapus karena sudah digunakan dalam stok opname.'
                ], 409);
            }

            $item->delete();

            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal menghapus barang: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus barang.',
            ], 500);
        }
    }

    /**
     * POST /api/stock/items/reorder
     * Pengaturan Urutan Barang
     */
    public function reorderItems(Request $request)
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|integer|exists:mkat_barang,nid',
            'item_ids'    => 'required|array|min:1',
            'item_ids.*'  => 'required|integer|exists:mbarang,nid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $company = $this->resolveCompany($request);
        $categoryId = $request->category_id;
        $itemIds = $request->item_ids;

        if ($company) {
            $categoryOwned = MkatBarang::where('nid', $categoryId)
                ->where('cperusahaan', $company)
                ->exists();

            if (!$categoryOwned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan.',
                ], 404);
            }
        }

        // Pastikan semua barang milik kategori yang dipilih
        $count = Mbarang::where('nid_kat', $categoryId)
            ->whereIn('nid', $itemIds)
            ->count();

        if ($count !== count($itemIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Beberapa barang tidak termasuk dalam kategori yang dipilih.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($itemIds as $index => $itemId) {
                Mbarang::where('nid', $itemId)->update([
                    'nurut' => $index + 1
                ]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Urutan barang berhasil diperbarui.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal memperbarui urutan barang: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui urutan barang.',
            ], 500);
        }
    }
}
