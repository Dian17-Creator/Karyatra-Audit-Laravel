<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Auth\Mdepartemen;
use App\Models\Audit\MkatTanya;
use App\Models\Audit\Mtanya;
use App\Models\Audit\Maudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuditDepartmentController extends Controller
{
    /**
     * Get all departments.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $departments = Mdepartemen::select('nid as id', 'cnama as name')
            ->orderBy('cnama')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data department berhasil diambil.',
            'data'    => $departments,
        ]);
    }

    /**
     * Get department to questions mapping.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function mapping($id): JsonResponse
    {
        $department = Mdepartemen::find($id);

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department tidak ditemukan.',
            ], 404);
        }

        $linkedQuestionIds = $department->auditQuestions()->pluck('mtanya.nid')->toArray();

        // Get all categories ordered by name
        $categories = MkatTanya::orderBy('cnama')->get();

        // Get all active questions ordered by sequence
        $questions = Mtanya::active()->orderBy('nurut')->get();
        $groupedQuestions = $questions->groupBy('nid_kat');

        $formattedCategories = [];

        foreach ($categories as $category) {
            $catQuestions = $groupedQuestions->get($category->nid, collect());

            $formattedQuestions = $catQuestions->map(function ($q) use ($linkedQuestionIds) {
                return [
                    'id'       => $q->nid,
                    'question' => $q->ctanya,
                    'linked'   => in_array($q->nid, $linkedQuestionIds),
                ];
            });

            $formattedCategories[] = [
                'id'        => $category->nid,
                'name'      => $category->cnama,
                'questions' => $formattedCategories,
            ];
        }

        return response()->json([
            'success' => true,
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
     * Update department to questions mapping.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeMapping(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'department_id'  => 'required|exists:mdepartemen,nid',
            'question_ids'   => 'required|array',
            'question_ids.*' => 'exists:mtanya,nid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $departmentId = $request->department_id;
        $questionIds = $request->question_ids;

        // Cek apakah Department tersebut sedang memiliki audit yang masih aktif
        $activeAudit = Maudit::where('nid_dept', $departmentId)
            ->whereIn('cstatus', ['Draft', 'In Progress'])
            ->exists();

        if ($activeAudit) {
            return response()->json([
                'success' => false,
                'message' => 'Pemetaan pertanyaan tidak dapat diubah karena department sedang digunakan dalam audit.'
            ], 409);
        }

        DB::beginTransaction();

        try {
            $department = Mdepartemen::find($departmentId);
            $department->auditQuestions()->sync($questionIds);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pemetaan pertanyaan berhasil disimpan.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal menyimpan pemetaan department: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan pemetaan.',
            ], 500);
        }
    }
}
