<?php

namespace App\Services;

use App\Models\Stock\Mopname;
use App\Models\Stock\TopnameHasil;
use App\Models\Stock\TopnameFoto;
use App\Models\Auth\Mdepartemen;
use App\Models\Stock\TdeptBarang;
use App\Models\Stock\Mbarang;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;

class StockReportService
{
    protected ImageUploadService $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function getList(
        ?int $departmentId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $auditorId = null,
        ?int $page = null,
        ?string $company = null
    ) {
        $query = Mopname::with([
            'department',
            'auditor'
        ])
            ->orderBy('started_at', 'desc');

        if ($company) {
            $query->whereHas('department', function ($q) use ($company) {
                $q->where('cperusahaan', $company);
            });
        }

        if ($auditorId) {
            $query->where('nid_auditor', $auditorId);
        }

        if ($departmentId) {
            $query->where('nid_dept', $departmentId);
        }

        if ($dateFrom) {
            $query->whereDate('daudit', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('daudit', '<=', $dateTo);
        }

        if ($page !== null) {
            $histories = $query->paginate(15, ['*'], 'page', $page);
            $items = $histories->getCollection()->map(function ($audit) {
                return $this->formatHistoryItem($audit);
            });

            return [
                'items' => $items->values(),
                'pagination' => [
                    'current_page' => $histories->currentPage(),
                    'last_page' => $histories->lastPage(),
                    'per_page' => $histories->perPage(),
                    'total' => $histories->total(),
                    'from' => $histories->firstItem(),
                    'to' => $histories->lastItem(),
                ]
            ];
        }

        $histories = $query->limit(1000)->get();
        $items = $histories->map(function ($audit) {
            return $this->formatHistoryItem($audit);
        });

        return [
            'items' => $items->values(),
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $items->count(),
                'total' => $items->count(),
                'from' => $items->isNotEmpty() ? 1 : null,
                'to' => $items->isNotEmpty() ? $items->count() : null,
            ]
        ];
    }

    private function formatHistoryItem(Mopname $audit)
    {
        return [
            'id' => $audit->nid,
            'document_id' => $audit->cdocid,
            'department_id' => $audit->nid_dept,
            'department_name' => $audit->department ? $audit->department->cnama : null,
            'auditor_id' => $audit->nid_auditor,
            'auditor_name' => $audit->auditor ? ($audit->auditor->cnamalengkap ?? $audit->auditor->cfullname) : null,
            'audit_date' => $audit->daudit ? $audit->daudit->format('Y-m-d') : null,
            'status' => $audit->cstatus,
            'auditee_name' => $audit->cauditee,
            'started_at' => $audit->started_at ? $audit->started_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $audit->updated_at ? $audit->updated_at->format('Y-m-d H:i:s') : null,
            'submitted_at' => $audit->submitted_at ? $audit->submitted_at->format('Y-m-d H:i:s') : null,
        ];
    }

    public function startStockOpname(int $departmentId, int $auditorId)
    {
        return DB::transaction(function () use ($departmentId, $auditorId) {
            $existing = Mopname::where('nid_dept', $departmentId)
                ->where('nid_auditor', $auditorId)
                ->where('cstatus', '<>', 'Submitted')
                ->orderBy('started_at', 'desc')
                ->first();

            if ($existing) {
                return [
                    'id' => $existing->nid,
                    'document_id' => $existing->cdocid,
                    'status' => $existing->cstatus,
                    'is_existing' => true
                ];
            }

            $department = Mdepartemen::findOrFail($departmentId);

            $deptName = strtolower(trim($department->cnama));
            $deptName = preg_replace("/[^a-z0-9]+/", "_", $deptName);
            $deptName = trim($deptName, "_");

            $docPrefix = "stok_" . $deptName . "_" . date("Ymd");
            $sequence = Mopname::where('cdocid', 'LIKE', $docPrefix . '_%')->count() + 1;
            $cdocid = sprintf("%s_%03d", $docPrefix, $sequence);

            $audit = Mopname::create([
                'cdocid' => $cdocid,
                'nid_dept' => $departmentId,
                'nid_auditor' => $auditorId,
                'daudit' => Carbon::today(),
                'cstatus' => 'Draft',
                'started_at' => Carbon::now()
            ]);

            $items = TdeptBarang::where('nid_dept', $departmentId)->pluck('nid_barang');

            if ($items->isEmpty()) {
                throw new Exception("Tidak ada daftar barang untuk departemen ini.");
            }

            $responses = [];
            $now = Carbon::now();
            foreach ($items as $itemId) {
                $responses[] = [
                    'nid_opname' => $audit->nid,
                    'nid_barang' => $itemId,
                    'nqty_stock' => null,
                    'nqty_real' => null,
                    'nselisih' => 0,
                    'nsel_kurang' => 0,
                    'nsel_lebih' => 0,
                    'fna' => 0,
                    'cket' => null,
                    'updated_at' => $now
                ];
            }

            TopnameHasil::insert($responses);

            return [
                'id' => $audit->nid,
                'document_id' => $audit->cdocid,
                'status' => $audit->cstatus,
                'is_existing' => false
            ];
        });
    }

    public function getDetail(int $auditId)
    {
        $audit = Mopname::with(['department', 'auditor'])
            ->where('nid', $auditId)
            ->firstOrFail();

        $responses = TopnameHasil::with(['item.group'])
            ->where('nid_opname', $audit->nid)
            ->get();

        $photos = TopnameFoto::whereIn('nid_hasil', $responses->pluck('nid'))
            ->get()
            ->groupBy('nid_hasil');

        $categoriesMap = [];

        foreach ($responses as $response) {
            $item = $response->item;
            if (!$item) continue;

            $category = $item->group;
            $categoryId = $category ? $category->nid : 0;
            $categoryName = $category ? $category->cnama : 'Uncategorized';

            if (!isset($categoriesMap[$categoryId])) {
                $categoriesMap[$categoryId] = [
                    'id' => $categoryId,
                    'name' => $categoryName,
                    'items' => []
                ];
            }

            $itemPhotos = isset($photos[$response->nid]) ? $photos[$response->nid]->map(function ($photo) {
                $path = $photo->cphoto_path;
                if (!str_starts_with($path, 'uploads/')) {
                    $path = 'uploads/' . ltrim($path, '/');
                }
                return [
                    'id' => $photo->nid,
                    'response_id' => $photo->nid_hasil,
                    'sequence' => $photo->nurut,
                    'photo_path' => asset($path),
                    'remark' => $photo->cket,
                    'action' => null,
                    'uploaded_at' => $photo->uploaded_at ? $photo->uploaded_at->format('Y-m-d H:i:s') : null
                ];
            })->toArray() : [];

            $categoriesMap[$categoryId]['items'][] = [
                'id' => $item->nid,
                'name' => $item->cbarang,
                'sequence' => $item->nurut,
                'response' => [
                    'id' => $response->nid,
                    'qty_stock' => $response->nqty_stock !== null ? (float) $response->nqty_stock : null,
                    'qty_real' => $response->nqty_real !== null ? (float) $response->nqty_real : null,
                    'diff' => (float) $response->nselisih,
                    'diff_under' => (float) $response->nsel_kurang,
                    'diff_over' => (float) $response->nsel_lebih,
                    'is_na' => (bool) $response->fna,
                    'remark' => $response->cket
                ],
                'photos' => $itemPhotos
            ];
        }

        usort($categoriesMap, function ($a, $b) {
            return $a['name'] <=> $b['name'];
        });

        foreach ($categoriesMap as &$cat) {
            usort($cat['items'], function ($a, $b) {
                return $a['sequence'] <=> $b['sequence'];
            });
        }

        return [
            'header' => [
                'id' => $audit->nid,
                'document_id' => $audit->cdocid,
                'department_id' => $audit->nid_dept,
                'department_name' => $audit->department ? $audit->department->cnama : null,
                'auditor_id' => $audit->nid_auditor,
                'auditor_name' => $audit->auditor ? ($audit->auditor->cnamalengkap ?? $audit->auditor->cfullname) : null,
                'status' => $audit->cstatus,
                'audit_date' => $audit->daudit ? $audit->daudit->format('Y-m-d') : null,
                'auditee_name' => $audit->cauditee,
                'verification_photo' => $audit->cphoto_path ? asset((!str_starts_with($audit->cphoto_path, 'uploads/') ? 'uploads/' : '') . $audit->cphoto_path) : null,
                'started_at' => $audit->started_at ? $audit->started_at->format('Y-m-d H:i:s') : null,
                'updated_at' => $audit->updated_at ? $audit->updated_at->format('Y-m-d H:i:s') : null,
                'submitted_at' => $audit->submitted_at ? $audit->submitted_at->format('Y-m-d H:i:s') : null,
            ],
            'categories' => array_values($categoriesMap)
        ];
    }

    public function updateAnswer(
        int $auditId,
        int $itemId,
        ?float $qtyStock,
        ?float $qtyReal,
        ?string $remark = null
    ) {
        $audit = Mopname::where('nid', $auditId)->firstOrFail();

        if ($audit->cstatus === 'Submitted') {
            throw new Exception("Stok opname sudah disubmit, tidak bisa diubah.", 403);
        }

        $response = TopnameHasil::where('nid_opname', $audit->nid)
            ->where('nid_barang', $itemId)
            ->firstOrFail();

        $updateData = ['updated_at' => Carbon::now()];

        if ($qtyStock !== null) {
            $updateData['nqty_stock'] = $qtyStock;
        }
        if ($qtyReal !== null) {
            $updateData['nqty_real'] = $qtyReal;
        }
        if ($remark !== null) {
            $updateData['cket'] = $remark;
        }

        $response->update($updateData);

        if ($audit->cstatus === 'Draft') {
            $audit->update(['cstatus' => 'In Progress', 'updated_at' => Carbon::now()]);
        } else {
            $audit->update(['updated_at' => Carbon::now()]);
        }

        return true;
    }

    public function uploadPhoto(int $responseId, UploadedFile $photo, ?string $remark = null, ?string $company = null)
    {
        return DB::transaction(function () use ($responseId, $photo, $remark, $company) {
            $responseInfo = TopnameHasil::findOrFail($responseId);

            $audit = Mopname::with('department')->where('nid', $responseInfo->nid_opname)->firstOrFail();

            if ($audit->cstatus === 'Submitted') {
                throw new Exception("Stok opname sudah disubmit, tidak bisa mengunggah foto.", 403);
            }

            $photoCount = TopnameFoto::where('nid_hasil', $responseInfo->nid)->count();
            if ($photoCount >= 5) {
                throw new Exception("Maksimal 5 foto per barang.", 400);
            }

            $nextSequence = TopnameFoto::where('nid_hasil', $responseInfo->nid)->max('nurut') ?? 0;
            $nextSequence++;

            if (!$company && $audit->department) {
                $company = $audit->department->cperusahaan;
            }

            $companyFolder = $company ? $this->imageService->companyFolderName($company) : null;
            $relativeDir = $companyFolder ? ($companyFolder . '/' . $audit->cdocid) : $audit->cdocid;

            $uploadDir = public_path('uploads/' . $relativeDir);
            if (!File::isDirectory($uploadDir)) {
                File::makeDirectory($uploadDir, 0775, true, true);
            }

            $item = Mbarang::findOrFail($responseInfo->nid_barang);
            $groupId = $item->nid_kat;

            $extension = 'jpg';
            $filename = $audit->cdocid . '_' . $groupId . '_' . $item->nid . '_' . $nextSequence . '.' . $extension;

            $absolutePath = $uploadDir . '/' . $filename;
            $relativePath = $relativeDir . '/' . $filename;

            $this->imageService->optimizeAndSave(
                $photo->getRealPath(),
                $absolutePath,
                $photo->getMimeType()
            );

            $photoRecord = TopnameFoto::create([
                'nid_hasil' => $responseInfo->nid,
                'nurut' => $nextSequence,
                'cket' => $remark,
                'cphoto_path' => $relativePath,
                'uploaded_at' => Carbon::now()
            ]);

            return [
                'id' => $photoRecord->nid,
                'photo_path' => asset('uploads/' . $relativePath)
            ];
        });
    }

    public function updatePhoto(int $photoId, ?string $remark)
    {
        $photo = TopnameFoto::findOrFail($photoId);
        $responseInfo = TopnameHasil::findOrFail($photo->nid_hasil);

        $audit = Mopname::where('nid', $responseInfo->nid_opname)->firstOrFail();

        if ($audit->cstatus === 'Submitted') {
            throw new Exception("Stok opname sudah disubmit, tidak bisa mengubah foto.", 403);
        }

        $photo->update(['cket' => $remark]);

        return true;
    }

    public function deletePhoto(int $photoId)
    {
        return DB::transaction(function () use ($photoId) {
            $photo = TopnameFoto::findOrFail($photoId);
            $responseInfo = TopnameHasil::findOrFail($photo->nid_hasil);

            $audit = Mopname::where('nid', $responseInfo->nid_opname)->firstOrFail();

            if ($audit->cstatus === 'Submitted') {
                throw new Exception("Stok opname telah di-submit, foto tidak bisa dihapus.", 403);
            }

            $path = $photo->cphoto_path;
            if (!str_starts_with($path, 'uploads/')) {
                $path = 'uploads/' . ltrim($path, '/');
            }
            $absolutePath = public_path($path);

            if (File::exists($absolutePath)) {
                File::delete($absolutePath);
            }

            $photo->delete();

            return true;
        });
    }

    public function submitStockOpname(int $auditId, string $auditeeName, UploadedFile $verificationPhoto, ?string $company = null)
    {
        return DB::transaction(function () use ($auditId, $auditeeName, $verificationPhoto, $company) {
            $audit = Mopname::with('department')->where('nid', $auditId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($audit->cstatus === 'Submitted') {
                throw new Exception("Stok opname sudah selesai.", 400);
            }

            $missingItems = TopnameHasil::where('nid_opname', $audit->nid)
                ->where(function ($query) {
                    $query->whereNull('nqty_stock')
                        ->orWhereNull('nqty_real');
                })->pluck('nid_barang');

            if ($missingItems->isNotEmpty()) {
                throw new HttpResponseException(
                    response()->json([
                        'success' => false,
                        'message' => 'Semua stok barang harus diisi.',
                        'errors' => [
                            'items' => $missingItems
                        ]
                    ], 422)
                );
            }

            if (!$company && $audit->department) {
                $company = $audit->department->cperusahaan;
            }

            $companyFolder = $company ? $this->imageService->companyFolderName($company) : null;
            $relativeDir = $companyFolder ? ($companyFolder . '/' . $audit->cdocid) : $audit->cdocid;

            $extension = 'jpg';
            $filename = $audit->cdocid . '_verification.' . $extension;

            $uploadDir = public_path('uploads/' . $relativeDir);
            if (!File::isDirectory($uploadDir)) {
                File::makeDirectory($uploadDir, 0775, true, true);
            }

            $absolutePath = $uploadDir . '/' . $filename;
            $relativePath = $relativeDir . '/' . $filename;

            $this->imageService->optimizeAndSave(
                $verificationPhoto->getRealPath(),
                $absolutePath,
                $verificationPhoto->getMimeType()
            );

            $responses = TopnameHasil::where('nid_opname', $audit->nid)->get();

            foreach ($responses as $resp) {
                $stock = (float) $resp->nqty_stock;
                $real = (float) $resp->nqty_real;

                $diff = round($real - $stock, 6);
                if (abs($diff) < 0.000001) $diff = 0;

                $diffUnder = 0;
                $diffOver = 0;

                if ($stock - $real >= 0.000001) {
                    $diffUnder = round($stock - $real, 6);
                } elseif ($real - $stock >= 0.000001) {
                    $diffOver = round($real - $stock, 6);
                }

                $resp->update([
                    'nselisih' => $diff,
                    'nsel_kurang' => $diffUnder,
                    'nsel_lebih' => $diffOver,
                    'updated_at' => Carbon::now()
                ]);
            }

            $audit->update([
                'cauditee' => trim($auditeeName),
                'cphoto_path' => $relativePath,
                'cstatus' => 'Submitted',
                'submitted_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            return [
                'auditee_name' => $audit->cauditee,
                'verification_photo' => asset('uploads/' . $relativePath)
            ];
        });
    }

    public function deleteStockOpname(int $auditId)
    {
        return DB::transaction(function () use ($auditId) {
            $audit = Mopname::where('nid', $auditId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($audit->cstatus === 'Submitted') {
                throw new Exception("Stok opname telah di-submit dan tidak bisa dihapus.", 403);
            }

            $responses = TopnameHasil::where('nid_opname', $audit->nid)->get();
            $responseIds = $responses->pluck('nid');

            $photos = TopnameFoto::whereIn('nid_hasil', $responseIds)->get();

            foreach ($photos as $photo) {
                $path = $photo->cphoto_path;
                if (!str_starts_with($path, 'uploads/')) {
                    $path = 'uploads/' . ltrim($path, '/');
                }
                $absolutePath = public_path($path);

                if (File::exists($absolutePath)) {
                    File::delete($absolutePath);
                }

                $photo->delete();
            }

            TopnameHasil::whereIn('nid', $responseIds)->delete();

            $auditDir = public_path('uploads/' . $audit->cdocid);
            if (File::isDirectory($auditDir)) {
                $files = File::files($auditDir);
                if (count($files) === 0) {
                    File::deleteDirectory($auditDir);
                }
            }

            $audit->delete();

            return true;
        });
    }
}
