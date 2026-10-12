<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\{Hash, Mail};
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    public const USER_PASSWORD = 'Test-Only@2026';

    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('İşlevsel testler yalnız yeni SQLite :memory: veritabanında çalışabilir.');
        }
        // Each refreshed application receives a new in-memory database. No existing schema is reset.
        $migrator = $this->app->make('migrator');
        $migrator->getRepository()->createRepository();
        $migrator->run(database_path('migrations'));
        config(['mail.default' => 'array']);
        Mail::fake();
    }

    protected function createUser(bool $verified = true, bool $superUser = false): User
    {
        $user = new User();
        $user->forceFill([
            'name' => 'Test Üyesi',
            'email' => 'test-'.Str::uuid().'@example.test',
            'password' => Hash::make(self::USER_PASSWORD),
            'email_verified_at' => $verified ? now() : null,
            'role' => $superUser ? 'super_user' : 'member',
            'locale' => 'tr',
        ])->save();
        return $user;
    }
}
