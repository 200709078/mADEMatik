<?php

namespace Database\Seeders;

use App\Services\DrawDownloadCatalog;
use Illuminate\Database\Seeder;

class DrawDownloadsSeeder extends Seeder
{
    public function run(): void
    {
        app(DrawDownloadCatalog::class)->import();
    }
}
