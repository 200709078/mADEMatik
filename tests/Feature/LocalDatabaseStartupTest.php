<?php

namespace Tests\Feature;

use App\Services\LocalDatabase;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class LocalDatabaseStartupTest extends TestCase
{
    private function configureLocalDatabase(): void
    {
        app()->detectEnvironment(fn () => 'local');
        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'mysql', 'database' => 'madematik_v2',
                'username' => 'madematik_v2_user', 'password' => 'Fake-Process-Only',
                'unix_socket' => storage_path('runtime/mariadb.sock'), 'url' => null,
            ],
            'site.db_ownership' => 'madematik_v2',
        ]);
    }

    private function event(string $command = 'serve'): CommandStarting
    {
        return new CommandStarting($command, new ArrayInput([]), new BufferedOutput);
    }

    public function test_local_serve_starts_only_the_owned_existing_database(): void
    {
        $this->configureLocalDatabase();
        File::shouldReceive('isDirectory')->once()->with(storage_path('runtime/mariadb/mysql'))->andReturn(true);
        Process::fake();
        $event = $this->event();

        LocalDatabase::startForServe($event);

        Process::assertRan(function (PendingProcess $process) {
            return $process->command === ['bash', base_path('scripts/dev-database.sh'), 'start']
                && $process->path === base_path() && $process->timeout === 30;
        });
        Process::assertRanTimes(['bash', base_path('scripts/dev-database.sh'), 'start'], 1);
        $this->assertStringContainsString('yerel veritabanı hazır', $event->output->fetch());
    }

    #[DataProvider('excludedEnvironments')]
    public function test_other_environments_never_start_a_database(string $environment): void
    {
        app()->detectEnvironment(fn () => $environment);
        Process::fake();
        LocalDatabase::startForServe($this->event());
        Process::assertNothingRan();
    }

    public static function excludedEnvironments(): array
    {
        return [['testing'], ['qa'], ['production']];
    }

    public function test_other_commands_never_start_a_database(): void
    {
        $this->configureLocalDatabase();
        Process::fake();
        foreach (['about', 'migrate', 'queue:work'] as $command) LocalDatabase::startForServe($this->event($command));
        Process::assertNothingRan();
    }

    #[DataProvider('unsafeConnections')]
    public function test_unsafe_connections_are_rejected_before_starting_a_process(array $overrides): void
    {
        $this->configureLocalDatabase();
        config($overrides);
        Process::fake();
        try {
            LocalDatabase::startForServe($this->event());
            $this->fail('Güvensiz bağlantı reddedilmeliydi.');
        } catch (\RuntimeException $error) {
            $this->assertNotEmpty($error->getMessage());
        }
        Process::assertNothingRan();
    }

    public static function unsafeConnections(): array
    {
        return [
            'foreign connection' => [['database.default' => 'sqlite']],
            'wrong driver' => [['database.connections.mysql.driver' => 'sqlite']],
            'wrong ownership' => [['site.db_ownership' => 'foreign_database']],
            'root user' => [['database.connections.mysql.username' => 'root']],
            'foreign schema' => [['database.connections.mysql.database' => 'awa']],
            'URL override' => [['database.connections.mysql.url' => 'mysql://example.test/foreign']],
            'read override' => [['database.connections.mysql.read' => ['host' => 'example.test']]],
            'write override' => [['database.connections.mysql.write' => ['host' => 'example.test']]],
            'system socket' => [['database.connections.mysql.unix_socket' => '/run/mysqld/mysqld.sock']],
        ];
    }

    public function test_even_an_owned_qa_socket_is_not_started_by_local_serve(): void
    {
        $this->configureLocalDatabase();
        config(['database.connections.mysql.unix_socket' => storage_path('runtime/qa-abcdef12/mysql.sock')]);
        Process::fake();
        $this->expectException(\RuntimeException::class);
        try { LocalDatabase::startForServe($this->event()); }
        finally { Process::assertNothingRan(); }
    }

    public function test_missing_initial_database_never_triggers_automatic_setup(): void
    {
        $this->configureLocalDatabase();
        File::shouldReceive('isDirectory')->once()->with(storage_path('runtime/mariadb/mysql'))->andReturn(false);
        Process::fake();
        $this->expectExceptionMessage('Yerel veritabanı kurulmamış');
        try { LocalDatabase::startForServe($this->event()); }
        finally { Process::assertNothingRan(); }
    }

    public function test_failed_database_start_blocks_serve_without_leaking_process_output(): void
    {
        $this->configureLocalDatabase();
        File::shouldReceive('isDirectory')->once()->with(storage_path('runtime/mariadb/mysql'))->andReturn(true);
        Process::fake(['*' => Process::result(errorOutput: 'Private diagnostic sentinel', exitCode: 1)]);
        try {
            LocalDatabase::startForServe($this->event());
            $this->fail('Başlangıç hatası web sunucusunu durdurmalıydı.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('MariaDB başlatılamadı', $error->getMessage());
            $this->assertStringNotContainsString('Private diagnostic sentinel', $error->getMessage());
        }
    }
}
