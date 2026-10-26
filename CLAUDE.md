# CLAUDE.md — mADEMatik Solutions (madematik.com)

madematik.com ana sitesi. Laravel 13 uygulaması doğrudan bu klasörün kökündedir (alt uygulama klasörü yok). Site **canlıda yayında**; kullanıcı diğer projelerini (drAW, drAW2D, drAW3D, awCELL, aWa…) bu sitede tanıtır ve indirme bağlantılarını buradan verir.

Kullanıcıyla Türkçe konuş. Ziyaretçiye görünen metinler Türkçe; kod, tablo ve değişken adları İngilizce.

## Kesin kurallar

- **Commit + push serbest** (biten, doğrulanmış iş; `main`'e normal push; bkz. `~/Belgeler/CLAUDE.md`). Canlı sunucuya (cPanel) dağıtım, DNS, SSL, gerçek e-posta alıcıları ve canlı veriler için her seferinde ayrı açık onay al.
- **Diğer projeler salt okunur:** `~/Belgeler` altındaki `aWa`, `drAW`, `drAW2D`, `drAW3D`, `awCELL`, `Learn English with ALA` vb. okunabilir; değiştirilmez, içlerinde bağımlılık kurma/migration/test çalıştırılmaz.
- **aWa** (`https://awa.madematik.com`) bağımsız uygulama, ayrı DB ve ayrı yönetim paneli. Ona DB bağlantısı, ortak SSO veya kod müdahalesi yok; ana site yalnız tanıtır ve bağlantı verir. Yazımı `aWa` (asla `AWay`).
- **Fiziksel public uygulama dizinleri korunur:** `/drAW`, `/drAW2D`, `/drAW3D`, `/awCELL` (`public/` altında; her biri kendi deposundan `npm run deploy` ile yüklenir) ve `awa`. Laravel `.htaccess` `-d`/`-f` kuralı bunları ezmemeli; canlıda bu dizinlere dokunan `rm`, `rsync --delete` vb. kullanılmaz.
- **Veritabanı:** Yalnız bu projenin DB'si (`madematik_v2`, kullanıcı `madematik_v2_user`). `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe` `app/Services/DatabaseSafety.php` tarafından engellenir — bu korumayı gevşetme. Seed mevcut veride tekrar çalıştırılmaz. Yıkıcı DB işleminden önce `.local/db-backups/` altına `mariadb-dump` yedeği al.
- `.env`, `.local/*.cnf` ve parolalar gizli: içeriklerini rapora/koda/commit'e yazma.
- **Marka görselleri** (`public/assets/brand/`) sabittir; MS sembolünü yeniden çizme, renk/geometri değiştirme. Paket raster'dır (PNG/WebP/ICO), SVG yok. Kaynak: `~/Belgeler/mADEMatik-Gorseller/` ve [docs/reference-notes.md](docs/reference-notes.md).
- Ürün bilgisi uydurma (özellik, istatistik, sahte yorum); bilinmiyorsa kullanıcıya sor veya boş durum kullan.

## Teknoloji

PHP 8.4, Laravel 13, Blade, Tailwind CSS 4 (`@tailwindcss/vite`), Alpine.js, Lucide ikonları (tek ikon seti), Inter fontu, Vite 8, MariaDB 11 (yerel) / 10.6 (canlı). Vue/Inertia/Livewire yok. Laravel Boost kurulu değil.

## Yerel ortam

- Sistem MariaDB servisi, TCP `127.0.0.1:3306`. Bağlantı bilgisi `.env` içinde; CLI için `mysql --defaults-extra-file=.local/system-project-db.cnf`.
- `DatabaseSafety` yerelde yalnız şu kombinasyona izin verir: `APP_ENV=local`, host `127.0.0.1`, port `3306`, DB `madematik_v2`, kullanıcı `madematik_v2_user`, boş `DB_SOCKET`, `DB_OWNERSHIP=madematik_v2`.
- Sunucu: `php artisan serve --host=127.0.0.1 --port=8000` (veya `composer dev`). Vite: `npm run dev` / `npm run build`.
- Adminer: `http://localhost:8080/adminer.php`.
- `storage/runtime/mariadb*` ve `scripts/dev-database.sh` eski özel MariaDB örneğine aittir (13 Eki 2026'da durduruldu) — kullanılmıyor.
- Yerel mail `.env`'de SMTP'ye açık (`MAIL_ALLOW_LOCAL_SEND=true`); gerçek adreslere test maili göndermekten kaçın. Kuyruk yerelde `sync`.
- `EMAIL_VERIFICATION_ENABLED=false` (kullanıcı talimatıyla geçici kapalı; kayıtta e-posta iki kez yazılır).

## Testler

```bash
vendor/bin/phpunit          # SQLite :memory:, geliştirme DB'sine dokunmaz
npm run test:clock          # Saat & Sayaç node testleri
```

Testleri ve Vite build'i aynı anda çalıştırma (build sırasında manifest geçici kaybolur). Ağır tarayıcı/QA koşuları (`scripts/browser-qa.mjs`, `scripts/prepare-qa.py`, [docs/qa-environment.md](docs/qa-environment.md)) yalnız gerektiğinde.

## Yapı

- `routes/web.php` — kamu sayfaları: `/`, `/hakkimda`, `/projelerim`, `/projelerim/{slug}`, `/yorumlar`, `/saat-sayac`, `/iletisim`, hukukî taslaklar, `/sitemap.xml`, `/robots.txt`. `routes/auth.php` — üyelik/profil/bildirim. `routes/admin.php` — `/admin` (`auth`, `verified`, `can:admin`, `password.confirm`).
- Roller: yalnız `member` ve `super_user` (`users.role`; `User::isSuperUser()`). Moderatör/editör/okul yöneticisi rolü eklenmez. Yetki sunucu tarafında (Gate `admin`, `CommentPolicy`). Super User yalnız `php artisan madematik:create-super-user` ile (etkileşimli parola).
- Modeller: `Project`, `ProjectCategory`, `ProjectMedia`, `ProjectDownload`, `Comment` (+ `CommentReport`), `ContactMessage`, `Setting`, `NotificationPreference`, `ActivityLog`, `User`.
- Servisler (`app/Services`): `Settings` (DB `settings` → yoksa `config/content.php` varsayılanı), `ModerationService`, `NotificationService`, `MediaService` (WebP yeniden kodlama), `AvatarService`, `Sitemap`, `DatabaseSafety`.
- **Yorumlar:** her yeni yorum/yanıt/düzenleme `pending`; yalnız Super User onayıyla yayınlanır. Otomatik yayın yok.
- **Çok dillilik:** içerik alanları `{tr, en}` JSON; çeviri dosyaları `lang/tr`, `lang/en`. Şu an yalnız TR yayında (`config/site.php` `available_locales`); EN çevrilmeden dil seçici/EN sayfa açılmaz.
- Komutlar: `madematik:create-super-user`, `madematik:generate-sitemap` (scheduler saatlik), `madematik:import-draw-downloads`, `madematik:initialize-local-db`. Saat & Sayaç projesi için `ClockProjectSeeder`.
- Proje indirmeleri: `project_downloads` (GitHub releases `latest/download` bağlantıları), [docs/project-downloads.md](docs/project-downloads.md).

## Tasarım sistemi

- Tokenlar `resources/css/app.css` (`:root` ve `[data-theme=dark]`): `--page`, `--surface`, `--text`, `--brand`, `--radius-*` vb. Sayfalarda rastgele renk/font/radius yazma; tokenları kullan.
- Ortak Blade bileşenleri `resources/views/components/ui/` (button, input, select, modal, alert, badge, card, table, tabs, dropdown, avatar, toast-stack…) ve `components/` (brand-logo, icon, project-card, comment-*, notification-bell, theme-toggle). Yeni ekranlar bunları kullanır.
- Açık/koyu tema: OS tercihinden başlar, seçim tarayıcıda saklanır. Toast'lar `madematik-toast` olayı (sağ alt). Frontend modern portfolyo; admin kompakt.
- Erişilebilirlik ve `prefers-reduced-motion` gözetilir; mobil uyum zorunlu.

## Kod stili

Mevcut PHP kodu sıkı/kompakt yazılmış (az boşluk, tek satırlık metotlar, gruplanmış `use`). Dokunduğun dosyanın üslubunu koru; dosyayı baştan formatlama (Pint'i tüm projeye çalıştırma).

## Canlı yayın

- Canlı: cPanel, `~/public_html` (Laravel kökü) ← `~/repositories/mADEMatik` (GitHub `200709078/mADEMatik`, `main`). Canlı DB adı `mademati_madematik_v2`.
- `public/build` **depoya commit edilir**: frontend değişikliğinden sonra `npm run build` çalıştırıp derlenmiş dosyaları da commit et.
- Sunucuda `./scripts/deploy-sync.sh ~/repositories/mADEMatik ~/public_html [taban-commit]`: yalnız değişen dosyaları kopyalar; migration veya `composer.lock` değişikliği varsa **durur** (ayrı onaylı işlem). `.env*`, `storage/`, `bootstrap/cache/`, bağımsız public dizinler kopyalanmaz. Sunucuda `optimize:clear` takılıyor; script `view:clear` / `config:clear` / `route:clear` kullanır.
- Migration gereken değişiklikleri kullanıcıya açıkça bildir; canlıda `php artisan migrate --force` kullanıcı tarafından ayrıca çalıştırılır.
- Ayrıntı: [docs/operations.md](docs/operations.md), [docs/production.env.example](docs/production.env.example), [docs/sitemap.md](docs/sitemap.md).

## Canlı DB'yi yerele alma

Kullanıcı phpMyAdmin dökümünü verir (ör. `~/Masaüstü/mademati_madematik_v2_CANLI_YEDEK.sql`; içinde `CREATE DATABASE`/`USE` yok, `DROP TABLE` yok). Adımlar: yerel `mariadb-dump` yedeği `.local/db-backups/`'a → `FOREIGN_KEY_CHECKS=0` ile `madematik_v2` tablolarını düşür → dökümü `--default-character-set=utf8mb4` ile içe aktar → satır sayılarını dökümle karşılaştır, `php artisan migrate:status` kontrol et. Yüklenen dosyalar (`storage/app/public/avatars`, proje medyası) DB'ye dahil değildir; yerelde eksik olabilir.
