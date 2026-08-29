<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Auth\Muser;
use App\Models\Auth\TaccountPurgeLog;
use App\Models\Auth\TaccountLifecycleEmailLog;
use App\Services\ImageUploadService;
use App\Mail\CompanyPurgeCompletedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class PurgeCompanyDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auditra:purge-companies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pembersihan otomatis data operasional perusahaan yang telah melewati masa tenggang penghapusan.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        if (!\Illuminate\Support\Facades\Schema::hasColumn('muser', 'ddeletionrequested')) {
            $this->info('Kolom ddeletionrequested belum tersedia pada tabel muser database ini.');
            return 0;
        }

        // Cari owner yang ddeletionrequested IS NOT NULL dan ddeleteafter <= NOW()
        $pendingOwners = Muser::whereNotNull('ddeletionrequested')
            ->whereNotNull('ddeleteafter')
            ->where('ddeleteafter', '<=', $now)
            ->get();

        if ($pendingOwners->isEmpty()) {
            $this->info('Tidak ada perusahaan yang perlu dipurge saat ini.');
            return 0;
        }

        $imageService = new ImageUploadService();

        foreach ($pendingOwners as $owner) {
            $company = $owner->cperusahaan;
            $ownerId = $owner->nid;
            $ownerName = $owner->cnamalengkap;
            $ownerEmail = $owner->cemail;
            $deletionRequested = $owner->ddeletionrequested;
            $deleteAfter = $owner->ddeleteafter;

            $companyFolder = $imageService->companyFolderName($company);

            $this->info("Memulai purge untuk perusahaan: {$company} (Owner: {$ownerEmail})");

            // 1. Catat ke taccount_purge_log dengan status 'processing'
            $purgeLog = TaccountPurgeLog::create([
                'ccompany_snapshot'        => $company,
                'cowner_name_snapshot'     => $ownerName,
                'cowner_email_snapshot'    => $ownerEmail,
                'ccompany_folder_snapshot' => $companyFolder,
                'ddeletionrequested'       => $deletionRequested,
                'ddeleteafter'             => $deleteAfter,
                'dexecuted'                => Carbon::now(),
                'cstatus'                  => 'processing',
            ]);

            try {
                DB::transaction(function () use ($company) {
                    // a. Hapus Foto & Hasil Audit
                    $auditIds = DB::table('maudit')->where('cperusahaan', $company)->pluck('nid');
                    if ($auditIds->isNotEmpty()) {
                        $hasilIds = DB::table('taudit_hasil')->whereIn('nidaudit', $auditIds)->pluck('nid');
                        if ($hasilIds->isNotEmpty()) {
                            DB::table('taudit_foto')->whereIn('nidhasil', $hasilIds)->delete();
                            DB::table('taudit_hasil')->whereIn('nidaudit', $auditIds)->delete();
                        }
                        DB::table('maudit')->where('cperusahaan', $company)->delete();
                    }

                    // b. Hapus Master Pertanyaan & Kategori Audit
                    $katTanyaIds = DB::table('mkat_tanya')->where('cperusahaan', $company)->pluck('nid');
                    if ($katTanyaIds->isNotEmpty()) {
                        $tanyaIds = DB::table('mtanya')->whereIn('nidkat', $katTanyaIds)->pluck('nid');
                        if ($tanyaIds->isNotEmpty()) {
                            DB::table('tdept_tanya')->whereIn('nidtanya', $tanyaIds)->delete();
                            DB::table('mtanya')->whereIn('nidkat', $katTanyaIds)->delete();
                        }
                        DB::table('mkat_tanya')->where('cperusahaan', $company)->delete();
                    }

                    // c. Hapus Foto & Hasil Stock Opname
                    $opnameIds = DB::table('mopname')->where('cperusahaan', $company)->pluck('nid');
                    if ($opnameIds->isNotEmpty()) {
                        $opnameHasilIds = DB::table('topname_hasil')->whereIn('nidopname', $opnameIds)->pluck('nid');
                        if ($opnameHasilIds->isNotEmpty()) {
                            DB::table('topname_foto')->whereIn('nidhasil', $opnameHasilIds)->delete();
                            DB::table('topname_hasil')->whereIn('nidopname', $opnameIds)->delete();
                        }
                        DB::table('mopname')->where('cperusahaan', $company)->delete();
                    }

                    // d. Hapus Master Barang & Kategori Stok
                    $katBarangIds = DB::table('mkat_barang')->where('cperusahaan', $company)->pluck('nid');
                    if ($katBarangIds->isNotEmpty()) {
                        $barangIds = DB::table('mbarang')->whereIn('nidkat', $katBarangIds)->pluck('nid');
                        if ($barangIds->isNotEmpty()) {
                            DB::table('tdept_barang')->whereIn('nidbarang', $barangIds)->delete();
                            DB::table('mbarang')->whereIn('nidkat', $katBarangIds)->delete();
                        }
                        DB::table('mkat_barang')->where('cperusahaan', $company)->delete();
                    }

                    // e. Hapus Departemen
                    DB::table('mdepartemen')->where('cperusahaan', $company)->delete();

                    // f. Hapus Seluruh User Non-Owner & Owner
                    DB::table('muser')->where('cperusahaan', $company)->delete();
                });

                // Hapus folder fisik foto perusahaan jika ada
                $folderPathPublic = storage_path("app/public/shared/auditra/public/{$companyFolder}");
                if (File::exists($folderPathPublic)) {
                    File::deleteDirectory($folderPathPublic);
                }

                $folderPathPublicWeb = public_path("shared/auditra/public/{$companyFolder}");
                if (File::exists($folderPathPublicWeb)) {
                    File::deleteDirectory($folderPathPublicWeb);
                }

                // Update purge log status ke 'completed'
                $purgeLog->cstatus = 'completed';
                $purgeLog->save();

                // Kirim email konfirmasi ke owner
                $logStatus = 'sent';
                $logError = null;

                try {
                    Mail::to($ownerEmail)->send(new CompanyPurgeCompletedMail($ownerName, $company));
                } catch (\Throwable $e) {
                    Log::error("Gagal mengirim email CompanyPurgeCompletedMail ke {$ownerEmail}: " . $e->getMessage());
                    $logStatus = 'failed';
                    $logError = $e->getMessage();
                }

                TaccountLifecycleEmailLog::create([
                    'nid_owner_snapshot'   => $ownerId,
                    'nid_purge_log'        => $purgeLog->nid,
                    'cevent'               => 'purge_completed',
                    'ccompany_snapshot'    => $company,
                    'cowner_name_snapshot' => $ownerName,
                    'cowner_email_snapshot' => $ownerEmail,
                    'cstatus'              => $logStatus,
                    'cerror'               => $logError,
                    'dsent'                => Carbon::now(),
                ]);

                $this->info("Purge berhasil untuk perusahaan: {$company}");

            } catch (\Throwable $e) {
                Log::error("Error saat purge perusahaan {$company}: " . $e->getMessage());
                $purgeLog->cstatus = 'failed';
                $purgeLog->cerror = $e->getMessage();
                $purgeLog->save();

                $this->error("Gagal purge perusahaan {$company}: " . $e->getMessage());
            }
        }

        return 0;
    }
}
