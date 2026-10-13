<?php

namespace App\Http\Controllers;

use App\Services\Sitemap;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(Sitemap $sitemap): Response
    {
        // Read current publication state rather than serving a stale cron snapshot.
        return response($sitemap->render(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function robots(Sitemap $sitemap): Response
    {
        $body = "User-agent: *\nDisallow: /\n";
        if (app()->isProduction()) {
            $body = "User-agent: *\nAllow: /\n";
            foreach (['/admin', '/profilim', '/bildirimler', '/giris', '/kayit', '/sifremi-unuttum',
                '/sifre-sifirla', '/eposta-dogrula', '/eposta-dogrulama-bildirimi', '/sifre-onayi', '/cikis'] as $path) {
                $body .= "Disallow: {$path}\n";
            }
            $body .= 'Sitemap: '.$sitemap->canonicalUrl('sitemap')."\n";
        }

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
