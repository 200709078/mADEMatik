<?php

namespace App\Services;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class LocalDatabase
{
    public static function startForServe(CommandStarting $event): void
    {
        if ($event->command !== 'serve' || ! app()->environment('local')) return;

        DatabaseSafety::assertOwnedConnection(config('database.default'));
        if (config('database.connections.mysql.unix_socket') !== storage_path('runtime/mariadb.sock')) {
            throw new \RuntimeException('Yerel web sunucusu yalnız bu projenin geliştirme veritabanını otomatik başlatabilir.');
        }
        // Starting the web server must never initialize a database or replace credentials.
        if (! File::isDirectory(storage_path('runtime/mariadb/mysql'))) {
            throw new \RuntimeException('Yerel veritabanı kurulmamış. İlk kurulum için ./scripts/dev-database.sh start çalıştırın.');
        }

        $result = Process::path(base_path())->timeout(30)->run(['bash', base_path('scripts/dev-database.sh'), 'start']);
        if (! $result->successful()) {
            throw new \RuntimeException('Projeye ait MariaDB başlatılamadı. ./scripts/dev-database.sh status ve özel MariaDB günlüğünü kontrol edin.');
        }
        $event->output->writeln('<info>mADEMatik yerel veritabanı hazır.</info>');
    }
}
