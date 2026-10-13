<?php

namespace App\Console\Commands;

use App\Services\DatabaseSafety;
use App\Services\Sitemap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateSitemap extends Command
{
    protected $signature = 'madematik:generate-sitemap';

    protected $description = 'Yalnızca yayımlanmış site adreslerinden özel sitemap dosyasını güvenli biçimde oluşturur.';

    public function handle(Sitemap $sitemap): int
    {
        try {
            $isolatedTest = app()->runningUnitTests() && config('database.default') === 'sqlite'
                && config('database.connections.sqlite.database') === ':memory:';
            if (! $isolatedTest) {
                DatabaseSafety::assertOwnedConnection(config('database.default'));
            }

            $count = $sitemap->generate();
            $this->info("Sitemap güncellendi: {$count} herkese açık adres.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Exception messages may contain connection details or SQL. Never echo or log them here.
            Log::error('Sitemap oluşturulamadı.', ['exception_type' => $exception::class]);
            $this->error('Sitemap oluşturulamadı. Özel uygulama günlüğünü ve bağlantı ayarlarını kontrol edin.');

            return self::FAILURE;
        }
    }
}
