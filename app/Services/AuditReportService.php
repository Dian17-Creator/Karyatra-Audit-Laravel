<?php

namespace App\Services;

use App\Models\Audit\Maudit;
use App\Models\Audit\TauditFoto;
use App\Models\Audit\TauditHasil;
use App\Models\Audit\Mtanya;
use App\Models\Auth\Mdepartemen;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Exception;
use Carbon\Carbon;

class AuditReportService
{
    /**
     * Get paginated list of audits.
     */
    public function getList(int $departmentId, string $dateFrom, string $dateTo, int $perPage = 15)
    {
        $audits = Maudit::with('department')
            ->where('nid_dept', $departmentId)
            ->whereBetween('daudit', [$dateFrom, $dateTo])
            ->orderBy('daudit', 'desc')
            ->orderBy('cdocid', 'desc')
            ->get();

        return $audits->map(function ($audit) {
            return [
                'nid' => $audit->nid,
                'cdocid' => $audit->cdocid,
                'nid_dept' => $audit->nid_dept,
                'department_name' => $audit->department->cnama ?? '-',
                'cstatus' => $audit->cstatus,
                'daudit' => $audit->daudit ? $audit->daudit->format('Y-m-d') : null,
                'created_at' => $audit->started_at
            ];
        });
    }

    /**
     * Get structured detail of an audit.
     */
    public function getDetail(int $auditId)
    {
        $audit = Maudit::with([
            'department',
            'auditor',
            'responses.question.category',
            'responses.photos'
        ])->findOrFail($auditId);

        // Group by category
        $categoriesMap = [];

        foreach ($audit->responses as $response) {
            $question = $response->question;
            if (!$question) continue;

            $category = $question->category;
            if (!$category) continue;

            $categoryId = $category->nid;

            if (!isset($categoriesMap[$categoryId])) {
                $categoriesMap[$categoryId] = [
                    'id' => $categoryId,
                    'name' => $category->cnama,
                    'total_score' => 0,
                    'max_score' => 0,
                    'percentage' => 0,
                    'questions' => []
                ];
            }

            $score = $response->nnilai !== null ? (float) $response->nnilai : 0;
            $categoriesMap[$categoryId]['total_score'] += $score;
            $categoriesMap[$categoryId]['max_score'] += 2; // Each question max 2

            $photos = $response->photos->map(function ($photo) {
                return [
                    'id' => $photo->nid,
                    'photo_path' => request()->getSchemeAndHttpHost() . '/' . ltrim($photo->cphoto_path, '/'),
                    'remark' => $photo->cket,
                    'action' => $photo->caction,
                ];
            })->toArray();

            $categoriesMap[$categoryId]['questions'][] = [
                'id' => $question->nid,
                'question' => $question->cquest,
                'response' => [
                    'id' => $response->nid,
                    'score' => $response->nnilai,
                    'is_na' => (bool) $response->fna,
                    'remark' => $response->cket,
                ],
                'photos' => $photos
            ];
        }

        // Calculate percentage for each category
        foreach ($categoriesMap as &$cat) {
            $cat['percentage'] = $cat['max_score'] > 0
                ? round(($cat['total_score'] / $cat['max_score']) * 100, 2)
                : 0;
        }

        return [
            'audit' => [
                'id' => $audit->nid,
                'document_id' => $audit->cdocid,
                'status' => $audit->cstatus,
                'audit_date' => $audit->daudit ? $audit->daudit->format('Y-m-d') : null,
                'department_name' => $audit->department ? $audit->department->cnama : null,
                'auditor_name' => $audit->auditor ? ($audit->auditor->cnamalengkap ?? $audit->auditor->cfullname) : null,
                'total_score' => (float) $audit->ntotnilai,
                'max_score' => (float) $audit->nnilaimax,
                'percentage' => (float) $audit->npersen,
                'verification_photo' => $audit->cphoto_path ? request()->getSchemeAndHttpHost() . '/' . ltrim($audit->cphoto_path, '/') : null,
                'auditee_name' => $audit->cauditee,
                'submitted_at' => $audit->submitted_at ? $audit->submitted_at->format('Y-m-d H:i:s') : null,
            ],
            'categories' => array_values($categoriesMap)
        ];
    }

    /**
     * Start a new audit.
     */
    public function startAudit(int $departmentId, int $auditorId)
    {
        return DB::transaction(function () use ($departmentId, $auditorId) {
            // Check existing Draft/In Progress audit today
            $existing = Maudit::where('nid_dept', $departmentId)
                ->where('nid_auditor', $auditorId)
                ->whereIn('cstatus', ['Draft', 'In Progress'])
                ->orderBy('started_at', 'desc')
                ->first();

            if ($existing) {
                throw new Exception("Audit yang belum disubmit sudah ada untuk departemen ini.");
            }

            $department = Mdepartemen::findOrFail($departmentId);

            // Generate Document ID
            $deptName = strtolower(trim($department->cnama));
            $deptName = preg_replace("/[^a-z0-9]+/", "_", $deptName);
            $deptName = trim($deptName, "_");

            $docPrefix = "audit_" . $deptName . "_" . date("Ymd");
            $sequence = Maudit::where('cdocid', 'LIKE', $docPrefix . '_%')->count() + 1;
            $cdocid = sprintf("%s_%03d", $docPrefix, $sequence);

            // Create Header
            $audit = Maudit::create([
                'cdocid' => $cdocid,
                'nid_dept' => $departmentId,
                'nid_auditor' => $auditorId,
                'daudit' => Carbon::today(),
                'cstatus' => 'Draft',
            ]);

            // Create blank responses based on mapping
            $questions = Mtanya::whereHas('departments', function ($q) use ($departmentId) {
                $q->where('mdepartemen.nid', $departmentId);
            })->get();

            if ($questions->isEmpty()) {
                throw new Exception("Tidak ada pertanyaan untuk departemen ini.");
            }

            $responses = [];
            $now = Carbon::now();
            foreach ($questions as $question) {
                $responses[] = [
                    'nid_audit' => $audit->nid,
                    'nid_tanya' => $question->nid,
                    'nnilai' => null,
                    'fna' => 0,
                    'cket' => null,
                    'updated_at' => $now
                ];
            }

            TauditHasil::insert($responses);

            return $audit;
        });
    }

    /**
     * Bulk update answers.
     */
    public function updateAnswers(int $auditId, array $answers)
    {
        DB::transaction(function () use ($auditId, $answers) {
            $audit = Maudit::findOrFail($auditId);

            if ($audit->cstatus === 'Submitted') {
                throw new Exception("Audit sudah disubmit, tidak bisa diubah.");
            }

            foreach ($answers as $answer) {
                TauditHasil::where('nid_audit', $auditId)
                    ->where(function ($q) use ($answer) {
                        $q->where('nid_tanya', $answer['question_id'])
                          ->orWhere('nid_tanya', $answer['question_id']);
                    })
                    ->update([
                        'nnilai' => isset($answer['score']) ? $answer['score'] : null,
                        'fna' => isset($answer['is_na']) && $answer['is_na'] ? 1 : 0,
                        'cket' => isset($answer['remark']) ? $answer['remark'] : null,
                        'updated_at' => Carbon::now()
                    ]);
            }

            if ($audit->cstatus === 'Draft') {
                $audit->update(['cstatus' => 'In Progress']);
            }
        });
    }

    /**
     * Submit audit.
     */
    public function submitAudit(int $auditId, string $auditeeName, string $verificationPhotoPath)
    {
        return DB::transaction(function () use ($auditId, $auditeeName, $verificationPhotoPath) {
            $audit = Maudit::findOrFail($auditId);

            if ($audit->cstatus === 'Submitted') {
                throw new Exception("Audit sudah selesai.");
            }

            // Validasi wajib jawab
            $unansweredCount = TauditHasil::where('nid_audit', $auditId)
                ->where('fna', 0)
                ->whereNull('nnilai')
                ->count();

            if ($unansweredCount > 0) {
                throw new Exception("Semua pertanyaan harus dijawab.");
            }

            // Hitung Score
            $totalScore = TauditHasil::where('nid_audit', $auditId)->sum('nnilai');
            $maxScore = TauditHasil::where('nid_audit', $auditId)->count() * 2;

            $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

            $audit->update([
                'cauditee' => $auditeeName,
                'cphoto_path' => $verificationPhotoPath,
                'cstatus' => 'Submitted',
                'submitted_at' => Carbon::now(),
                'ntotnilai' => $totalScore,
                'nnilaimax' => $maxScore,
                'npersen' => $percentage
            ]);

            return $audit;
        });
    }

    /**
     * Delete audit.
     */
    public function deleteAudit(int $auditId)
    {
        DB::transaction(function () use ($auditId) {
            $audit = Maudit::lockForUpdate()->findOrFail($auditId);

            if ($audit->cstatus === 'Submitted') {
                throw new Exception("Audit selesai, tidak bisa dihapus.");
            }

            // Ambil semua foto terkait
            $photoPaths = TauditFoto::whereHas('response', function ($q) use ($auditId) {
                $q->where('nid_audit', $auditId);
            })->pluck('cphoto_path')->toArray();

            // Hapus tabel secara berurutan
            TauditFoto::whereHas('response', function ($q) use ($auditId) {
                $q->where('nid_audit', $auditId);
            })->delete();

            TauditHasil::where('nid_audit', $auditId)->delete();
            $audit->delete();

            // Hapus file fisik
            foreach ($photoPaths as $path) {
                if (!$path) continue;
                $absolutePath = public_path($path);
                if (File::exists($absolutePath)) {
                    File::delete($absolutePath);
                }
            }

            // Hapus directory audit jika kosong
            $auditDir = public_path('uploads/' . $audit->cdocid);
            if (File::isDirectory($auditDir)) {
                $files = File::files($auditDir);
                if (count($files) === 0) {
                    File::deleteDirectory($auditDir);
                }
            }
        });
    }
}
