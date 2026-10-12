<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use PDO;
class InitializeLocalDatabase extends Command
{
    protected $signature='madematik:initialize-local-db';
    protected $description='Yeni ve boş, projeye özel MariaDB örneğinde ayrı uygulama veritabanı oluşturur.';
    public function handle(): int
    {
        $socket=storage_path('runtime/mariadb.sock');
        if (!app()->environment('local') || !file_exists($socket) || is_link(storage_path('runtime')) || is_link(storage_path('runtime/mariadb'))) {
            $this->error('Yalnız yeni, projeye özel yerel MariaDB örneği kurulabilir.'); return self::FAILURE;
        }
        try {
            $pdo=new PDO('mysql:unix_socket='.$socket,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $datadir=rtrim($pdo->query('SELECT @@datadir')->fetchColumn(),'/');
            if (realpath($datadir)!==realpath(storage_path('runtime/mariadb'))) throw new \RuntimeException();
            $count=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name NOT IN ('information_schema','performance_schema','mysql','sys')")->fetchColumn();
            if ($count!==0) { $this->error('Örnekte uygulama veritabanı var. Kurulum tekrar uygulanmadı.'); return self::FAILURE; }
            $envPath=base_path('.env');
            if (!file_exists($envPath)) { $this->error('Önce .env.example dosyasından .env oluşturun.'); return self::FAILURE; }
            $appPassword=bin2hex(random_bytes(24)); $rootPassword=bin2hex(random_bytes(24));
            $pdo->exec("CREATE DATABASE madematik_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("CREATE USER 'madematik_v2_user'@'localhost' IDENTIFIED BY ".$pdo->quote($appPassword));
            $pdo->exec("GRANT ALL PRIVILEGES ON madematik_v2.* TO 'madematik_v2_user'@'localhost'");
            $pdo->exec("DELETE FROM mysql.global_priv WHERE User=''");
            $pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$pdo->quote($rootPassword));
            $pdo->exec('FLUSH PRIVILEGES');
            $env=file_get_contents($envPath);
            foreach (['DB_CONNECTION'=>'mysql','DB_DATABASE'=>'madematik_v2','DB_USERNAME'=>'madematik_v2_user','DB_PASSWORD'=>$appPassword,'DB_SOCKET'=>$socket,'DB_OWNERSHIP'=>'madematik_v2'] as $key=>$value) {
                $line=$key.'='.$value;
                $env=preg_match('/^'.preg_quote($key,'/').'=/m',$env)?preg_replace('/^'.preg_quote($key,'/').'=.*$/m',$line,$env):$env."\n".$line."\n";
            }
            file_put_contents($envPath,$env,LOCK_EX); chmod($envPath,0600);
            if (!is_dir(base_path('.local'))) mkdir(base_path('.local'),0700,true);
            $rootFile=base_path('.local/db-root.cnf');
            file_put_contents($rootFile,"[client]\nuser=root\npassword=$rootPassword\nsocket=$socket\n",LOCK_EX); chmod($rootFile,0600);
            $this->info('Yeni mADEMatik veritabanı ve ayrı kullanıcı oluşturuldu. Gizli değerler yalnız yerel dosyalarda saklandı.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Yerel DB kurulumu tamamlanamadı. Veri dizini korunuyor; tekrar çalıştırmadan önce özel örnek erişimini ve kurulum durumunu inceleyin.');
            return self::FAILURE;
        }
    }
}
