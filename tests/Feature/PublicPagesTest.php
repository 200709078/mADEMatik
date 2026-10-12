<?php

namespace Tests\Feature;

use App\Models\{Comment, Project, ProjectCategory};
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    private function project(string $slug = 'test-projesi', bool $published = true): Project
    {
        $category = ProjectCategory::firstOrCreate(['slug' => 'test-kategorisi'], ['name' => ['tr' => 'Test Kategorisi', 'en' => 'Test Category']]);
        return Project::create([
            'project_category_id' => $category->id,
            'title' => ['tr' => 'Test Projesi', 'en' => 'Test Project'],
            'slug' => $slug, 'summary' => ['tr' => 'Yalnızca test için açıklama.'],
            'description' => ['tr' => 'Yalnızca test için ayrıntılı açıklama.'],
            'features' => ['tr' => ['Test özelliği']], 'technologies' => ['Laravel'],
            'status' => 'developing', 'is_published' => $published,
        ]);
    }

    public function test_public_pages_have_turkish_metadata_and_original_brand_assets(): void
    {
        foreach (['/', '/hakkimda', '/projelerim', '/yorumlar', '/saat-sayac', '/iletisim', '/gizlilik', '/kullanim-kosullari', '/uyelik-aydinlatma'] as $path) {
            $response = $this->get($path)->assertOk();
            $response->assertSee('<html lang="tr"', false)
                ->assertSee('name="description" content="', false)
                ->assertSee('rel="canonical" href="'.url($path).'"', false)
                ->assertSee('/assets/brand/icons/favicon.ico', false)
                ->assertSee('/assets/brand/logos/logo-header-light.png', false)
                ->assertSee('/assets/brand/logos/logo-header-dark.png', false)
                ->assertSee('/assets/brand/social/og-madematik-1200x630.png', false)
                ->assertDontSee('fonts.bunny.net', false)
                ->assertDontSee('fonts.googleapis.com', false);
        }
    }

    public function test_home_slider_uses_three_existing_responsive_assets_and_manual_controls(): void
    {
        $response = $this->get('/')->assertOk();
        foreach (['01-madematik', '02-projelerim', '03-egitim-teknoloji'] as $name) {
            $response->assertSee('/assets/brand/banners/slider-'.$name.'.webp', false)
                ->assertSee('/assets/brand/banners/slider-'.$name.'-mobile.webp', false);
        }
        $response->assertSee('fetchpriority="high"', false)->assertSee('Slaytlar düğmelerle değiştirilir.');
        $response->assertDontSee('Özgeçmiş')->assertDontSee('Yazılarım')->assertDontSee('Blog');
    }

    public function test_unpublished_project_is_not_exposed_in_pages_or_sitemap(): void
    {
        $project = $this->project('gizli-test-projesi', false);
        $this->get('/projelerim/'.$project->slug)->assertNotFound();
        $this->get('/projelerim')->assertOk()->assertDontSee($project->slug);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee($project->slug);
    }

    public function test_project_filters_preserve_queries_and_project_detail_metadata(): void
    {
        $project = $this->project();
        $this->get('/projelerim?q=Test&category=test-kategorisi&status=developing')->assertOk()->assertSee('Test Projesi');
        $this->get('/projelerim?q=Bulunmayan')->assertOk()->assertSee('Bu aramaya uygun proje yok');
        $this->get('/projelerim/'.$project->slug)->assertOk()
            ->assertSee('Test Projesi | mADEMatik Solutions')
            ->assertSee('name="description" content="Yalnızca test için açıklama."', false)
            ->assertSee('Test özelliği')->assertSee('Laravel')
            ->assertSee('/assets/brand/placeholders/proje-varsayilan-1200x675.webp', false);
    }

    public function test_approved_comment_is_escaped_and_verified_members_can_use_reply_and_report_controls(): void
    {
        $user = $this->createUser();
        $comment = new Comment();
        $comment->forceFill(['user_id' => $user->id, 'body' => '<script>alert("test")</script>', 'status' => 'approved', 'approved_at' => now()])->save();
        $this->actingAs($user)->get('/yorumlar')->assertOk()
            ->assertSee('<script>alert("test")</script>')
            ->assertDontSee('<script>alert("test")</script>', false)
            ->assertSee(':aria-expanded="replyOpen"', false)
            ->assertSee('Yanıtla')->assertSee('Bildir')
            ->assertSee('Super User onayından sonra görünür.');
    }

    public function test_english_routes_and_language_switcher_are_not_published(): void
    {
        foreach (['/en', '/en/about', '/en/projects', '/en/contact'] as $path) {
            $this->get($path)->assertNotFound();
        }
        $this->get('/')->assertDontSee('hreflang="en"', false)->assertDontSee('Dil seç', false);
    }

    public function test_sitemap_and_nonproduction_robots_respect_scope(): void
    {
        $project = $this->project();
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee(url('/hakkimda'))->assertSee(url('/projelerim/'.$project->slug))
            ->assertDontSee('/admin')->assertDontSee('/profilim')->assertDontSee('/en/');
        $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /", false);
    }

    public function test_account_pages_are_excluded_from_indexing(): void
    {
        foreach (['/giris', '/kayit', '/sifremi-unuttum'] as $path) {
            $this->get($path)->assertOk()->assertSee('name="robots" content="noindex,nofollow"', false);
        }
        $this->actingAs($this->createUser())->get('/profilim')->assertOk()->assertSee('name="robots" content="noindex,nofollow"', false);
    }
}
