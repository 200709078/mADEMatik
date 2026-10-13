<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Services\Sitemap;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    private ?string $temporaryDirectory = null;

    protected function tearDown(): void
    {
        if ($this->temporaryDirectory !== null) {
            (new Filesystem)->deleteDirectory($this->temporaryDirectory);
        }
        parent::tearDown();
    }

    private function project(string $slug, bool $published = true): Project
    {
        return Project::create([
            'title' => ['tr' => 'Özel proje başlığı'],
            'slug' => $slug,
            'description' => ['tr' => 'private-description-marker'],
            'external_url' => 'https://awa.example.test/private-school?token=private-link-marker',
            'is_published' => $published,
        ]);
    }

    private function isolateSnapshot(): Sitemap
    {
        $this->temporaryDirectory = base_path('.local/sitemap-qa-'.Str::uuid());
        $this->app->useStoragePath($this->temporaryDirectory.'/storage');

        return app(Sitemap::class);
    }

    private function locations(string $xml): array
    {
        $document = simplexml_load_string($xml, options: LIBXML_NONET);
        $this->assertNotFalse($document, 'Sitemap geçerli XML olmalıdır.');
        $this->assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $document->getDocNamespaces()['']);
        $document->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return array_map(fn ($location) => (string) $location, $document->xpath('/sm:urlset/sm:url/sm:loc'));
    }

    public function test_sitemap_contains_only_explicit_public_urls_and_published_project_urls(): void
    {
        config(['app.url' => 'https://madematik.example']);
        $project = $this->project('acik-proje');
        $this->project('gizli-proje', false);
        $this->project('invalid-slug<&private');
        $this->createUser();

        $response = $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame([
            'https://madematik.example/',
            'https://madematik.example/hakkimda',
            'https://madematik.example/projelerim',
            'https://madematik.example/yorumlar',
            'https://madematik.example/saat-sayac',
            'https://madematik.example/iletisim',
            'https://madematik.example/projelerim/'.$project->slug,
        ], $this->locations($response->getContent()));

        foreach (['gizli-proje', 'invalid-slug', 'private-description-marker', 'private-link-marker', 'awa.example.test',
            '/admin', '/profilim', '/bildirimler', '/giris', '/kayit', '/en/', '@example.test', 'users', 'sessions'] as $private) {
            $response->assertDontSee($private, false);
        }
        $response->assertSee('<lastmod>'.$project->updated_at->toAtomString().'</lastmod>', false);
    }

    public function test_sitemap_ignores_host_headers_and_query_string_data(): void
    {
        config(['app.url' => 'https://madematik.example']);
        $this->project('guvenli-proje');

        $response = $this->withHeaders([
            'Host' => 'attacker.example',
            'X-Forwarded-Host' => 'forwarded-attacker.example',
        ])->get('/sitemap.xml?email=private-query-marker&token=private-query-token')->assertOk();

        foreach ($this->locations($response->getContent()) as $location) {
            $this->assertStringStartsWith('https://madematik.example/', $location);
            $this->assertStringNotContainsString('?', $location);
        }
        $response->assertDontSee('attacker.example', false)->assertDontSee('private-query-', false);
    }

    public function test_generator_reads_only_the_two_public_project_columns_without_mutations(): void
    {
        $this->project('acik-proje');
        $queries = [];
        DB::listen(function ($event) use (&$queries) {
            $queries[] = $event->sql;
        });

        app(Sitemap::class)->render();

        $this->assertCount(1, $queries);
        $this->assertSame('select "slug", "updated_at" from "projects" where "is_published" = ? order by "id" asc', $queries[0]);
    }

    public function test_unpublishing_removes_project_immediately_even_when_cron_snapshot_is_older(): void
    {
        $sitemap = $this->isolateSnapshot();
        $project = $this->project('gecmis-acik-proje');
        $sitemap->generate();
        $this->assertStringContainsString($project->slug, file_get_contents($sitemap->snapshotPath()));

        $project->update(['is_published' => false]);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee($project->slug, false);
        $this->assertStringContainsString($project->slug, file_get_contents($sitemap->snapshotPath()));
    }

    public function test_command_atomically_updates_a_private_snapshot_without_temporary_files(): void
    {
        $sitemap = $this->isolateSnapshot();
        $this->project('ilk-proje');

        $this->artisan('madematik:generate-sitemap')->expectsOutput('Sitemap güncellendi: 7 herkese açık adres.')->assertSuccessful();
        $this->assertFileExists($sitemap->snapshotPath());
        $this->assertSame($sitemap->render(), file_get_contents($sitemap->snapshotPath()));
        $this->assertSame(0600, fileperms($sitemap->snapshotPath()) & 0777);
        $this->assertStringStartsWith($this->temporaryDirectory.'/storage/app/private/', $sitemap->snapshotPath());
        $this->assertStringNotContainsString(public_path(), $sitemap->snapshotPath());

        $this->project('ikinci-proje');
        $this->artisan('madematik:generate-sitemap')->expectsOutput('Sitemap güncellendi: 8 herkese açık adres.')->assertSuccessful();
        $this->assertCount(8, $this->locations(file_get_contents($sitemap->snapshotPath())));
        $this->assertSame([], glob(dirname($sitemap->snapshotPath()).'/.sitemap-*'));
    }

    public function test_failed_generation_preserves_previous_snapshot_and_prints_no_exception_details(): void
    {
        $sitemap = $this->isolateSnapshot();
        $sitemap->generate();
        $before = file_get_contents($sitemap->snapshotPath());
        config(['app.url' => 'https://private-user-marker:private-password-marker@madematik.example']);
        Log::shouldReceive('error')->once()->with('Sitemap oluşturulamadı.', ['exception_type' => RuntimeException::class]);

        $this->artisan('madematik:generate-sitemap')
            ->expectsOutput('Sitemap oluşturulamadı. Özel uygulama günlüğünü ve bağlantı ayarlarını kontrol edin.')
            ->assertFailed();

        $this->assertSame($before, file_get_contents($sitemap->snapshotPath()));
        $this->assertSame([], glob(dirname($sitemap->snapshotPath()).'/.sitemap-*'));
    }

    public function test_invalid_canonical_origins_are_rejected(): void
    {
        foreach (['javascript:alert(1)', 'https://madematik.example?private-token=1', 'https://madematik.example/#private',
            'https://madematik.example/private', 'https://private-user@madematik.example', "https://madematik.example\nattacker"] as $origin) {
            config(['app.url' => $origin]);
            try {
                app(Sitemap::class)->render();
                $this->fail('Geçersiz APP_URL kabul edilmemelidir.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Sitemap için APP_URL geçerli bir http/https site kökü olmalıdır.', $exception->getMessage());
            }
        }
    }

    public function test_robots_uses_canonical_sitemap_and_excludes_account_and_admin_paths_in_production(): void
    {
        config(['app.url' => 'https://madematik.example']);
        $this->app['env'] = 'production';

        $response = $this->get('https://madematik.example/robots.txt')->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee("Allow: /\n", false)
            ->assertSee("Sitemap: https://madematik.example/sitemap.xml\n", false);

        foreach (['/admin', '/profilim', '/bildirimler', '/giris', '/kayit', '/sifremi-unuttum', '/sifre-sifirla', '/eposta-dogrula', '/sifre-onayi'] as $path) {
            $response->assertSee("Disallow: {$path}\n", false);
        }
    }

    public function test_nonproduction_robots_disallows_all_crawling(): void
    {
        $this->get('/robots.txt')->assertOk()->assertContent("User-agent: *\nDisallow: /\n");
    }

    public function test_sitemap_schedule_is_hourly_only_in_production_and_prevents_overlap(): void
    {
        app(Kernel::class)->bootstrap();
        $events = array_values(array_filter(app(Schedule::class)->events(), fn ($event) => str_contains($event->command ?? '', 'madematik:generate-sitemap')));

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame('0 * * * *', $event->getExpression());
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(['production'], $event->environments);
        $this->assertFalse($event->runsInEnvironment('local'));
        $this->assertFalse($event->runsInEnvironment('testing'));
        $this->assertTrue($event->runsInEnvironment('production'));
    }
}
