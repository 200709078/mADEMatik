<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use XMLWriter;

class Sitemap
{
    private const PUBLIC_ROUTES = ['home', 'about', 'projects.index', 'comments.index', 'clock', 'contact'];

    public function canonicalUrl(string $route, array $parameters = []): string
    {
        // Only the configured origin is trusted; request headers never supply sitemap hosts.
        $origin = rtrim((string) config('app.url'), '/');
        $parts = parse_url($origin);

        if (filter_var($origin, FILTER_VALIDATE_URL) === false || ! is_array($parts)
            || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || ! empty($parts['path']) || preg_match('/[\s\x00-\x1F\x7F\\\\]/', $origin)) {
            throw new RuntimeException('Sitemap için APP_URL geçerli bir http/https site kökü olmalıdır.');
        }

        return $origin.route($route, $parameters, false);
    }

    public function render(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNS(null, 'urlset', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach (self::PUBLIC_ROUTES as $route) {
            $this->writeUrl($xml, $this->canonicalUrl($route));
        }

        // Do not load project descriptions, files, users, comments or other private tables.
        foreach (DB::table('projects')->where('is_published', true)->orderBy('id')->select(['slug', 'updated_at'])->cursor() as $project) {
            if (! is_string($project->slug) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $project->slug)) {
                continue;
            }

            $modified = $project->updated_at ? Carbon::parse($project->updated_at)->toAtomString() : null;
            $this->writeUrl($xml, $this->canonicalUrl('projects.show', ['slug' => $project->slug]), $modified);
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    public function snapshotPath(): string
    {
        return storage_path('app/private/seo/sitemap.xml');
    }

    public function generate(): int
    {
        // Render first so a database or configuration failure preserves the previous snapshot.
        $xml = $this->render();
        $path = $this->snapshotPath();
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Özel sitemap dizini oluşturulamadı.');
        }

        $temporary = tempnam($directory, '.sitemap-');
        if ($temporary === false) {
            throw new RuntimeException('Sitemap geçici dosyası oluşturulamadı.');
        }

        try {
            if (! chmod($temporary, 0600) || file_put_contents($temporary, $xml, LOCK_EX) !== strlen($xml)
                || ! rename($temporary, $path)) {
                throw new RuntimeException('Özel sitemap dosyası güncellenemedi.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return substr_count($xml, '<url>');
    }

    private function writeUrl(XMLWriter $xml, string $url, ?string $modified = null): void
    {
        $xml->startElement('url');
        $xml->writeElement('loc', $url);
        if ($modified !== null) {
            $xml->writeElement('lastmod', $modified);
        }
        $xml->endElement();
    }
}
