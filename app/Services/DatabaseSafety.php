<?php
namespace App\Services;

use Illuminate\Console\Events\CommandStarting;

class DatabaseSafety
{
    public static function guard(CommandStarting $event): void
    {
        if (in_array($event->command,['db:wipe','migrate:fresh','migrate:refresh','migrate:reset'],true)) {
            throw new \RuntimeException('Veri koruma kuralı: toplu/yıkıcı veritabanı komutları bu projede engellidir.');
        }
        if (!in_array($event->command,['migrate','migrate:install','migrate:rollback','db:seed','schema:dump','madematik:create-super-user'],true)) return;
        $connection=config('database.default');
        if ($event->command!=='madematik:create-super-user') $connection=$event->input->getOption('database') ?: $connection;
        self::assertOwnedConnection($connection);
    }

    public static function assertOwnedConnection(mixed $connection): void
    {
        if (!is_string($connection) || $connection!=='mysql') throw new \RuntimeException('Veritabanı sahipliği doğrulanmadı. Yalnız yeni mADEMatik veritabanına işlem yapılabilir.');
        $db=config('database.connections.'.$connection);
        $hasUrl=isset($db['url']) && $db['url']!=='';
        if ($hasUrl || !empty($db['read']) || !empty($db['write'])) {
            throw new \RuntimeException('Veritabanı sahipliği: DB_URL ve ayrı okuma/yazma hedefleri desteklenmez. Açık, tek bir proje bağlantısı yapılandırın.');
        }
        if (($db['driver']??null)!=='mysql' || config('site.db_ownership')!==($db['database']??null) || !preg_match('/^(?:[a-z0-9]+_)?madematik_v2(?:_[a-z0-9_]+)?$/',$db['database']??'') || in_array($db['username']??'',['','root'],true)) {
            throw new \RuntimeException('Veritabanı sahipliği doğrulanmadı. Yalnız yeni mADEMatik veritabanına işlem yapılabilir.');
        }
        $socket=$db['unix_socket']??'';
        $qaSocketPattern='~^'.preg_quote(storage_path('runtime'),'~').'/qa-[a-f0-9]+/mysql\.sock$~';
        $localTcp=app()->environment('local') && $socket==='' && ($db['host']??null)==='127.0.0.1'
            && (string)($db['port']??'')==='3306' && ($db['database']??null)==='madematik_v2'
            && ($db['username']??null)==='madematik_v2_user';
        if (!app()->isProduction() && !$localTcp && $socket!==storage_path('runtime/mariadb.sock') && !preg_match($qaSocketPattern,$socket)) {
            throw new \RuntimeException('Yerel geliştirme ve testte yalnız projeye ait MariaDB soketi veya açıkça tanımlı yerel proje TCP bağlantısı kullanılabilir.');
        }
    }
}
