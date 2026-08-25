<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

use App\Models\Auth\Mdepartemen;
use App\Models\Audit\Mtanya;
use App\Models\Audit\MkatTanya;
use App\Models\Audit\TdeptTanya;
use App\Models\Audit\Maudit;

class AuditDepartmentController extends Controller
{
    /**
     * Get all departments.
     *
     * GET /api/audit/departments
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
     * Get department to audit questions mapping.
     *
     * GET /api/audit/departments/{id}/mapping
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
         * Ambil ID pertanyaan yang sudah terhubung
         * dengan department tersebut langsung dari pivot table.
         */
        $linkedQuestionIds = TdeptTanya::where(
            'nid_dept',
            $department->nid
        )
            ->pluck('nid_tanya')
            ->toArray();

        /*
         * Ambil semua kelompok/category pertanyaan milik perusahaan.
         */
        $catQuery = MkatTanya::orderBy('cnama');
        if ($company) {
            $catQuery->where('cperusahaan', $company);
        }
        $categories = $catQuery->get();

        $categoryIds = $categories->pluck('nid')->toArray();

        /*
         * Ambil semua pertanyaan aktif milik kategori perusahaan.
         */
        $questions = Mtanya::active()
            ->whereIn('nid_kat', $categoryIds)
            ->orderBy('nurut')
            ->orderBy('ctanya')
            ->get();

        /*
         * Kelompokkan pertanyaan berdasarkan nid_kat.
         */
        $groupedQuestions = $questions->groupBy('nid_kat');

        $formattedCategories = [];

        foreach ($categories as $category) {
            $categoryQuestions = $groupedQuestions->get(
                $category->nid,
                collect()
            );

            $formattedQuestions = $categoryQuestions->map(function ($q) use ($linkedQuestionIds) {
                return [
                    'id'       => $q->nid,
                    'question' => $q->ctanya,
                    'name'     => $q->ctanya,
                    'sequence' => $q->nurut,
                    'linked'   => in_array(
                        $q->nid,
                        $linkedQuestionIds
                    ),
                ];
            })->values();

            $formattedCategories[] = [
                'id'        => $category->nid,
                'name'      => $category->cnama,
                'questions' => $formattedQuestions,
                'items'     => $formattedQuestions,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Data mapping pertanyaan berhasil diambil.',
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
     * Store / update department to audit questions mapping.
     *
     * POST /api/audit/departments/mapping
     */
    public function storeMapping(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'department_id'  => 'required|integer|exists:mdepartemen,nid',
            'question_ids'   => 'nullable|array',
            'question_ids.*' => [
                'integer',
                'exists:mtanya,nid',
            ],
            'item_ids'       => 'nullable|array',
            'item_ids.*'     => [
                'integer',
                'exists:mtanya,nid',
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
         * Ambil question_ids atau item_ids, hilangkan duplicate ID.
         */
        $rawIds = $request->input('question_ids') ?? $request->input('item_ids', []);
        $questionIds = collect($rawIds)
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        /*
         * CEK AUDIT AKTIF
         */
        $activeAudit = Maudit::where(
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
                'message' => 'Pemetaan pertanyaan tidak dapat diubah karena department sedang digunakan dalam audit.',
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
            TdeptTanya::where(
                'nid_dept',
                $departmentId
            )->delete();

            /*
             * Masukkan mapping baru.
             */
            if (!empty($questionIds)) {
                $mappingData = [];

                foreach ($questionIds as $qId) {
                    $mappingData[] = [
                        'nid_dept'  => $departmentId,
                        'nid_tanya' => $qId,
                    ];
                }

                TdeptTanya::insert($mappingData);
            }

            DB::commit();

            /*
             * Ambil kembali mapping setelah berhasil disimpan.
             */
            $savedQuestionIds = TdeptTanya::where(
                'nid_dept',
                $departmentId
            )
                ->pluck('nid_tanya')
                ->map(fn($id) => (int) $id)
                ->values()
                ->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Pemetaan pertanyaan berhasil disimpan.',
                'data'    => [
                    'department' => [
                        'id'   => $department->nid,
                        'name' => $department->cnama,
                    ],
                    'question_ids'    => $savedQuestionIds,
                    'item_ids'        => $savedQuestionIds,
                    'total_questions' => count($savedQuestionIds),
                    'total_items'     => count($savedQuestionIds),
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error(
                'Gagal menyimpan pemetaan pertanyaan department.',
                [
                    'department_id' => $departmentId,
                    'question_ids'  => $questionIds,
                    'error'         => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan pemetaan pertanyaan.',
            ], 500);
        }
    }
}
