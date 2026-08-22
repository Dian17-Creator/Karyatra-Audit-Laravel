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
     * Mengambil data pohon hierarki (kategori beserta barang di dalamnya)
     */
    public function index()
    {
        try {
            $categories = MkatBarang::with(['items' => function ($query) {
                $query->orderBy('nurut');
            }])
                ->orderBy('nid')
                ->get()
                ->map(function ($cat) {
                    return [
                        'id' => $cat->nid,
                        'name' => $cat->cnama,
                        'description' => $cat->cket,
                        'items' => $cat->items->map(function ($item) {
                            return [
                                'id' => $item->nid,
                                'category_id' => $item->nid_kat,
                                'name' => $item->cbarang,
                                'sequence' => $item->nurut,
                            ];
                        }),
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Data pohon kategori dan barang berhasil diambil.',
                'data' => $categories,
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
        if (empty($request->cnama)) {
            return response()->json([
                'success' => false,
                'message' => 'Nama kategori harus diisi.'
            ], 400);
        }

        try {
            $cperusahaan = $request->input('cperusahaan') 
                ?? $request->input('company');

            if (empty($cperusahaan)) {
                $user = auth()->user() ?? $request->user();
                
                $userId = $request->input('user_id') 
                    ?? $request->input('auditor_id') 
                    ?? $request->input('nid_auditor')
                    ?? $request->input('nid_user');

                if (!$user && $userId) {
                    $user = \App\Models\Auth\Muser::find($userId);
                }

                if ($user) {
                    $cperusahaan = $user->cperusahaan ?? $user->ccompany;
                }
            }

            if (empty($cperusahaan)) {
                $firstUser = \App\Models\Auth\Muser::whereNotNull('cperusahaan')->first();
                $cperusahaan = $firstUser ? $firstUser->cperusahaan : 'Default';
            }

            $category = MkatBarang::create([
                'cnama' => $request->cnama,
                'cket' => $request->cket,
                'cperusahaan' => $cperusahaan,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kategori berhasil ditambahkan.',
                'data' => [
                    'id' => $category->nid,
                    'name' => $category->cnama,
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
        $nid = $id ?? $request->nid;
        $cnama = $request->cnama;

        if (empty($nid) || empty($cnama)) {
            return response()->json([
                'success' => false,
                'message' => 'ID kategori dan Nama kategori harus diisi.'
            ], 400);
        }

        try {
            $category = MkatBarang::find($nid);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan.'
                ], 404);
            }

            $category->update([
                'cnama' => $cnama,
                'cket' => $request->cket,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kategori berhasil diperbarui.',
                'data' => [
                    'id' => $category->nid,
                    'name' => $category->cnama,
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
        $nid = $id ?? $request->nid;

        if (empty($nid)) {
            return response()->json([
                'success' => false,
                'message' => 'ID kategori harus diisi.'
            ], 400);
        }

        try {
            $category = MkatBarang::find($nid);

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
                ->where('mbarang.nid_kat', $nid)
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
    public function getItems($categoryId)
    {
        try {
            $category = MkatBarang::find($categoryId);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan.',
                ], 404);
            }

            $items = Mbarang::where('nid_kat', $categoryId)
                ->orderBy('nurut')
                ->orderBy('nid')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->nid,
                        'category_id' => $item->nid_kat,
                        'name' => $item->cbarang,
                        'sequence' => $item->nurut,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Data barang berhasil diambil.',
                'data' => [
                    'category' => [
                        'id' => $category->nid,
                        'name' => $category->cnama,
                        'description' => $category->cket,
                    ],
                    'items' => $items,
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
        $nid_kat = $request->nid_kat ?? $request->nid_grp ?? $request->category_id;
        $cbarang = $request->cbarang ?? $request->citemname ?? $request->name;

        if (empty($nid_kat) || empty($cbarang)) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori dan Nama barang harus diisi.'
            ], 400);
        }

        try {
            $categoryExists = MkatBarang::where('nid', $nid_kat)->exists();
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
                'nurut' => $nurut,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil ditambahkan.',
                'data' => [
                    'id' => $item->nid,
                    'category_id' => $item->nid_kat,
                    'name' => $item->cbarang,
                    'sequence' => $item->nurut,
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
        $nid = $id ?? $request->nid;

        if (empty($nid)) {
            return response()->json([
                'success' => false,
                'message' => 'ID barang harus diisi.'
            ], 400);
        }

        try {
            $item = Mbarang::find($nid);

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barang tidak ditemukan.'
                ], 404);
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
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|integer|exists:mkat_barang,nid',
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'required|integer|exists:mbarang,nid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $categoryId = $request->category_id;
        $itemIds = $request->item_ids;

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
