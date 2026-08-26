<?php

namespace App\Http\Controllers;

use App\Models\Audit\MkatTanya;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AuditCategoryController extends Controller
{
    /**
     * GET /api/audit/categories
     */
    public function index(Request $request)
    {
        $company = $this->resolveCompany($request);

        $query = MkatTanya::withCount('questions');

        if ($company) {
            $query->where('cperusahaan', $company);
        }

        $categories = $query->orderBy('nid')
            ->get()
            ->map(function ($item) {
                return [
                    'id'             => $item->nid,
                    'name'           => $item->cnama,
                    'description'    => $item->cket,
                    'question_count' => $item->questions_count,
                    'created_at'     => $item->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Data kategori berhasil diambil.',
            'data'    => $categories,
        ]);
    }

    /**
     * POST /api/audit/categories
     */
    public function store(Request $request)
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $company = $this->resolveCompany($request);

        if (empty($company)) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi perusahaan tidak valid. Pengguna/perusahaan tidak ditemukan.',
            ], 422);
        }

        $category = MkatTanya::create([
            'cnama'       => $request->name,
            'cket'        => $request->description,
            'cperusahaan' => $company,
            'created_at'  => now(),
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
    }

    /**
     * PUT /api/audit/categories/{id}
     */
    public function update(Request $request, int|string $id)
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        Log::info('UPDATE KATEGORI AUDIT', [
            'id'   => $id,
            'data' => $request->all(),
        ]);

        $company = $this->resolveCompany($request);

        $query = MkatTanya::where('nid', $id);
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

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $category->update([
            'cnama' => $request->name,
            'cket'  => $request->description,
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
    }

    /**
     * DELETE /api/audit/categories/{id}
     */
    public function destroy(Request $request, int|string $id)
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        Log::info('DELETE KATEGORI AUDIT', [
            'id' => $id,
        ]);

        $company = $this->resolveCompany($request);

        $query = MkatTanya::withCount('questions')->where('nid', $id);
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

        if ($category->questions_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori masih memiliki pertanyaan dan tidak dapat dihapus.'
            ], 400);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus.'
        ]);
    }

    /**
     * GET /api/audit/categories/{id}
     */
    public function show(Request $request, int|string $id)
    {
        $company = $this->resolveCompany($request);

        $query = MkatTanya::withCount('questions')->where('nid', $id);
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

        return response()->json([
            'success' => true,
            'message' => 'Detail kategori berhasil diambil.',
            'data'    => [
                'id'             => $category->nid,
                'name'           => $category->cnama,
                'description'    => $category->cket,
                'question_count' => $category->questions_count,
                'created_at'     => $category->created_at,
            ],
        ]);
    }
}
