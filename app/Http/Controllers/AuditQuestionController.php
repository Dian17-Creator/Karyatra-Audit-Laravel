<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Audit\Mtanya;
use App\Models\Audit\MkatTanya;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuditQuestionController extends Controller
{
    /**
     * Display a listing of active questions for a given audit category.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $categoryId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $categoryId): JsonResponse
    {
        $company = $this->resolveCompany($request);

        if ($company) {
            $categoryOwned = MkatTanya::where('nid', $categoryId)
                ->where('cperusahaan', $company)
                ->exists();

            if (!$categoryOwned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan.',
                ], 404);
            }
        }

        $questions = Mtanya::where('nid_kat', $categoryId)
            ->active()
            ->orderBy('nurut')
            ->get()
            ->map(function ($item) {
                return [
                    'id'          => $item->nid,
                    'category_id' => $item->nid_kat,
                    'question'    => $item->ctanya,
                    'sequence'    => $item->nurut,
                    'active'      => $item->factive,
                    'created_at'  => $item->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Data pertanyaan berhasil diambil.',
            'data'    => $questions,
        ]);
    }

    /**
     * Store a newly created audit question.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|integer|exists:maudit_kat,nid',
            'question'    => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $company = $this->resolveCompany($request);

        if ($company) {
            $categoryOwned = MkatTanya::where('nid', $request->category_id)
                ->where('cperusahaan', $company)
                ->exists();

            if (!$categoryOwned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan.',
                ], 404);
            }
        }

        DB::beginTransaction();

        try {
            $nextSequence = Mtanya::where('nid_kat', $request->category_id)->max('nurut');
            $nextSequence = $nextSequence ? $nextSequence + 1 : 1;

            $question = Mtanya::create([
                'nid_kat'    => $request->category_id,
                'ctanya'     => $request->question,
                'nurut'      => $nextSequence,
                'factive'    => 1,
                'created_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pertanyaan berhasil ditambahkan.',
                'data'    => [
                    'id'          => $question->nid,
                    'category_id' => $question->nid_kat,
                    'question'    => $question->ctanya,
                    'sequence'    => $question->nurut,
                    'active'      => $question->factive,
                    'created_at'  => $question->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal menambahkan pertanyaan audit: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan pertanyaan.',
            ], 500);
        }
    }

    /**
     * Update the specified audit question's text only.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $company = $this->resolveCompany($request);
        $question = Mtanya::find($id);

        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'Pertanyaan tidak ditemukan.',
            ], 404);
        }

        if ($company) {
            $categoryOwned = MkatTanya::where('nid', $question->nid_kat)
                ->where('cperusahaan', $company)
                ->exists();

            if (!$categoryOwned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pertanyaan tidak ditemukan.',
                ], 404);
            }
        }

        if ($question->responses()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Pertanyaan tidak dapat diubah karena sudah digunakan dalam audit.'
            ], 409);
        }

        $question->ctanya = $request->question;
        $question->save();

        Log::info('Pertanyaan audit diperbarui.', [
            'id'      => $question->nid,
            'user_id' => optional($request->user())->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pertanyaan berhasil diperbarui.',
            'data'    => [
                'id'          => $question->nid,
                'category_id' => $question->nid_kat,
                'question'    => $question->ctanya,
                'sequence'    => $question->nurut,
                'active'      => $question->factive,
                'created_at'  => $question->created_at,
            ],
        ], 200);
    }

    /**
     * Delete the specified audit question and reorder remaining questions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $question = Mtanya::find($id);

        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'Pertanyaan tidak ditemukan.',
            ], 404);
        }

        if ($company) {
            $categoryOwned = MkatTanya::where('nid', $question->nid_kat)
                ->where('cperusahaan', $company)
                ->exists();

            if (!$categoryOwned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pertanyaan tidak ditemukan.',
                ], 404);
            }
        }

        if ($question->responses()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Pertanyaan tidak dapat dihapus karena sudah digunakan dalam audit.'
            ], 409);
        }

        DB::beginTransaction();

        try {
            $categoryId = $question->nid_kat;

            $question->delete();

            $remainingQuestions = Mtanya::where('nid_kat', $categoryId)
                ->orderBy('nurut', 'asc')
                ->get();

            $sequence = 1;
            foreach ($remainingQuestions as $remaining) {
                $remaining->nurut = $sequence;
                $remaining->save();
                $sequence++;
            }

            DB::commit();

            Log::info('Pertanyaan audit dihapus dan urutan diperbarui.', [
                'id'          => $id,
                'category_id' => $categoryId,
                'user_id'     => optional($request->user())->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pertanyaan berhasil dihapus.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal menghapus pertanyaan audit: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus pertanyaan.',
            ], 500);
        }
    }

    /**
     * Reorder the questions within a category based on question IDs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reorder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id'    => 'required|integer|exists:maudit_kat,nid',
            'question_ids'   => 'required|array|min:1',
            'question_ids.*' => 'required|integer|exists:mtanya,nid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $company = $this->resolveCompany($request);

        if ($company) {
            $categoryOwned = MkatTanya::where('nid', $request->category_id)
                ->where('cperusahaan', $company)
                ->exists();

            if (!$categoryOwned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak ditemukan.',
                ], 404);
            }
        }

        // Pastikan semua question_id benar-benar milik category yang dikirim
        $count = Mtanya::where('nid_kat', $request->category_id)
            ->whereIn('nid', $request->question_ids)
            ->count();

        if ($count !== count($request->question_ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Beberapa pertanyaan tidak termasuk dalam kategori yang dipilih.',
            ], 422);
        }

        $hasUsedQuestion = Mtanya::whereIn('nid', $request->question_ids)
            ->has('responses')
            ->exists();

        if ($hasUsedQuestion) {
            return response()->json([
                'success' => false,
                'message' => 'Urutan pertanyaan tidak dapat diubah karena terdapat pertanyaan yang sudah digunakan dalam audit.'
            ], 409);
        }

        DB::beginTransaction();

        try {
            foreach ($request->question_ids as $index => $questionId) {
                Mtanya::where('nid', $questionId)
                    ->update([
                        'nurut' => $index + 1,
                    ]);
            }

            DB::commit();

            Log::info('Urutan pertanyaan audit diperbarui.', [
                'category_id'  => $request->category_id,
                'question_ids' => $request->question_ids,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Urutan pertanyaan berhasil diperbarui.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Gagal memperbarui urutan pertanyaan audit.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui urutan pertanyaan.',
            ], 500);
        }
    }
}
