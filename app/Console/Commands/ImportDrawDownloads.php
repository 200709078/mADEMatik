<?php

namespace App\Console\Commands;

use App\Services\DatabaseSafety;
use App\Services\DrawDownloadCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportDrawDownloads extends Command
{
    protected $signature = 'madematik:import-draw-downloads';

    protected $description = 'Doğrulanmış drAW paket bilgilerini mevcut kayıtları koruyarak taslak olarak ekler.';

    public function handle(DrawDownloadCatalog $catalog): int
    {
        try {
            $isolatedTest = app()->runningUnitTests() && config('database.default') === 'sqlite'
                && config('database.connections.sqlite.database') === ':memory:';
            if (! $isolatedTest) {
                DatabaseSafety::assertOwnedConnection(config('database.default'));
            }
            if (! Schema::hasTable('project_downloads')) {
                $this->error('İndirme tablosu hazır değil. Önce yalnız bu projenin yeni migration dosyasını uygulayın.');
                return self::FAILURE;
            }
            if (! \App\Models\Project::where('slug', 'draw')->exists()) {
                $this->error('drAW proje kaydı bulunamadı. Önce projeyi oluşturun.');
                return self::FAILURE;
            }

            $count = $catalog->import();
            $this->info("{$count} yeni drAW indirme taslağı eklendi. Mevcut kayıtlar korundu.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('drAW indirme taslakları eklenemedi.', ['exception_type' => $exception::class]);
            $this->error('İndirme taslakları eklenemedi. Proje veritabanı bağlantısını ve migration durumunu kontrol edin.');

            return self::FAILURE;
        }
    }
}
