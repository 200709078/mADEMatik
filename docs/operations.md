# Yerel çalışma ve canlı yayın hazırlığı

Bu belge bir işletim planıdır. Üretim sunucusu, domain, DNS, cPanel, SSL ve mevcut bağımsız uygulamalarda hiçbir işlem yapılmadı. Canlı ortama uygulanacak bütün değişiklikler ayrıca açık kullanıcı onayı gerektirir.

## Yeni uygulamanın veritabanı

Yerel özel MariaDB örneği yalnızca bu proje için oluşturulur:

- Veri dizini: `storage/runtime/mariadb/`.
- Unix soketi: `storage/runtime/mariadb.sock`; `--skip-networking` ile TCP erişimi yoktur.
- Veritabanı: `madematik_v2`; ayrı kullanıcı: `madematik_v2_user`.
- İşletim: `./scripts/dev-database.sh start`, `status` ve `stop`.
- Kimlik bilgileri yalnızca `.env` dosyasında tutulur. Runtime, `.env`, yüklenen medya ve yedekler sürüm kontrolüne alınmaz.

Başlatma/bitiş betiği yalnızca kendi soket/PID/veri dizinini hedefler; sistem MariaDB servisini durdurmaz. Kapatmadan önce PID'nin yürütücüsü ve tam özel datadir/socket argümanları doğrulanır; yalnız TERM gönderilir. Veri dizini, soket veya PID belirsizliğinde çalışmaya devam edilmez. Yalnız yeni boş veri dizini `mariadb-install-db --auth-root-authentication-method=normal --skip-test-db` ile hazırlanır; ardından `php artisan madematik:initialize-local-db` yeni uygulamanın ayrı DB/kullanıcısını oluşturur. Mevcut dolu dizine tekrar kurulum uygulanmaz.

Migration güvenlik denetimi varsayılan veya sahipliği doğrulanmamış veritabanına yazmayı engeller. Yerelde `DB_OWNERSHIP=madematik_v2`, seçilen DB adı ve projeye ait tam Unix soketi doğrulanır; başarısız denetimi aşmak için başka bir projeden ayar kopyalanmaz.

`php artisan migrate --seed` yalnızca yeni izole mADEMatik veritabanında ilk kurulum için kullanılır. Mevcut veritabanlarında `migrate:fresh` ve `db:wipe` kullanılmaz. Seed gerçek kullanıcı/admin hesapları eklemez. Yeni yönetici `php artisan madematik:create-super-user` etkileşimli komutuyla oluşturulur; otomatik veya raporda yazılı parola yoktur.

Üretimde cPanel'in oluşturduğu yeni veritabanı ve yalnızca bu veritabanına yetkili ayrı kullanıcı kullanılmalıdır. cPanel hesap önekleri yerel adlardan farklı olabilir; hedef ad ve sahiplik onaylı kurulum sırasında açıkça doğrulanır. aWa veya eski ana site veritabanı adı/hesabı hiçbir bağlantıda kullanılmaz.

## Ortam ve gizli bilgiler

`.env.example` anahtarların belgesidir; gerçek sır içermez. Yeni kurulumda uygulamanın kendi `APP_KEY` değeri üretilir. Daha sonra bu anahtarı değiştirmek mevcut şifreli verileri ve oturumları etkiler; bir sürüm güncellemesinde yeniden üretilmez.

| Alan | Yerel değer/ilkesi | Üretim ilkesi |
| --- | --- | --- |
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | Yerelde açık olabilir | `false` |
| `APP_URL` | `http://127.0.0.1:8000` | Onaylanmış HTTPS ana adres |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE` | `tr` | Türkçe varsayılan; EN yayınlanmaz |
| `DB_CONNECTION` | `mysql` | Ayrı MariaDB/MySQL |
| `DB_SOCKET` | Proje özel Unix soketi | Hosting bağlantısına göre |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Yalnız proje örneği | Yeni DB ve sınırlı ayrı kullanıcı |
| `DB_OWNERSHIP` | `madematik_v2` sahiplik işareti | Yeni DB hedefi onaylı kurulumda doğrulanır |
| `MAIL_MAILER` | `log` | Onaylı gerçek SMTP |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` | Gerçek SMTP kullanılmaz | Hosting/SMTP sağlayıcısının değerleri |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Yerel/test gönderici | Doğrulanmış kurumsal gönderici |
| `QUEUE_CONNECTION` | `sync` | `database` ve çalışan cron/worker |
| `SESSION_SECURE_COOKIE` | HTTP geliştirmeye uygun | HTTPS için `true` |

Üretim dışı ortamlar gerçek mail gönderemez; uygulama log mailer'a yönlendirir. Mail logları mesaj ve bağlantı içerebilir; yalnız geliştirme kullanıcılarıyla çalışılır ve log erişimi kısıtlanır. Hesap doğrulama/parola sıfırlama kritik mesajları isteğe bağlı proje duyurularından ayrı tutulur.

Kurumsal iletişim adresi ve sosyal bağlantılar başlangıçta boş olabilir; yönetici gerçek bilgileri panelden girmelidir. Varsayımsal adres veya kişisel biyografi üretim bilgisi olarak kullanılmaz. Hukukî metinler taslaktır ve yayın öncesi kullanıcı tarafından gözden geçirilmelidir.

## Hesap ve kişisel veri silme

Profildeki silme talebi mevcut parola ve ayrı onay kutusuyla yöneticiye iletilir; talep hesabı hemen silmez. Super User talebi ve saklama koşullarını değerlendirir, üye ekranındaki ayrıca onaylanan kalıcı silme işlemini uygular. Super User hesapları bu ekrandan silinemez.

Mevcut uygulama anonimleştirme yapmaz: kullanıcı hesabı silindiğinde ona ait yorumlar ve bu yorumlara bağlı yanıtlar foreign key cascade ile kalıcı silinir. Bağlı yanıtların başka üyeler tarafından yazılmış olması bu sonucu değiştirmez. Yorum şikâyetleri ve bildirim tercihleri de ilişkilerine göre silinir; kullanıcıya ait site bildirimleri, oturumlar ve parola sıfırlama kaydı açıkça temizlenir. Ayrı iletişim mesajları ve işlem kayıtları otomatik tam kişisel veri temizliği sayılmaz; yönetici bunlardaki bilgileri ve saklama yükümlülüklerini ayrıca değerlendirmelidir. Yönetici onayından önce bu silme kapsamı gözden geçirilmelidir.

## Mail ve kuyruk işletimi

Yerelde `QUEUE_CONNECTION=sync` işler hemen yürür; `MAIL_MAILER=log` gerçek alıcıya mesaj göndermez. Üretimde kuyruk tablosu migration ile oluşturulur; e-posta işleri ve bildirimler için `QUEUE_CONNECTION=database` seçilir. Kuyruk bağlantısı değiştirildiğinde çalışan işleyicinin ve yapılandırma önbelleğinin aynı sürümü kullandığı doğrulanır.

Paylaşımlı cPanel sunucusunda sürekli worker desteklenmiyorsa her dakika sınırlı süreli bir işleyici çalıştırılabilir. Aşağıdaki yol ve PHP sürümü örnektir; gerçek hesap yolları onaylı hosting incelemesinde belirlenir. Kilit dosyası özel uygulama storage içinde kalır.

```cron
* * * * * /usr/bin/flock -n /home/ACCOUNT/apps/madematik/storage/framework/queue-worker.lock /usr/local/bin/php /home/ACCOUNT/apps/madematik/artisan queue:work database --stop-when-empty --max-time=50 --tries=3 >> /home/ACCOUNT/apps/madematik/storage/logs/queue-worker.log 2>&1
```

`flock` desteği yoksa hostingin eşdeğer çakışma kilidi veya yönetilen worker çözümü kullanılmalı; aynı kuyruğu sınırsız paralel süreçlerle tüketmekten kaçınılmalıdır. Job timeout değeri bağlantının `retry_after` değerinden kısa olmalıdır. Süre sınırı 60 saniyelik cron aralığının altında kalır. Gönderim başarısızlıkları `php artisan queue:failed` ile izlenir; tekrar denemeden önce SMTP sorunu düzeltilir ve mükerrer gönderim riski değerlendirilir. Worker çalışmıyorsa kurulum tamamlanmış sayılmaz; kuyruk derinliği ve başarısız işler takip edilir.

Uygulama zamanlanmış görev kullanıyorsa ayrıca tek cron tanımlanır:

```cron
* * * * * /usr/local/bin/php /home/ACCOUNT/apps/madematik/artisan schedule:run >> /home/ACCOUNT/apps/madematik/storage/logs/scheduler.log 2>&1
```

SMTP değerleri, gerçek alıcılar ve DNS/SPF/DKIM ayarları ancak canlı yayın hazırlığına yönelik açık onay kapsamında uygulanır. Kapsamlı testler sahte mail/log veya kullanıcının onayladığı test SMTP üzerinde yürür.

## cPanel ve dosya yerleşimi

Laravel uygulama kaynakları, `vendor`, `.env`, `storage` ve yedekler web kökü dışında kalmalıdır. Web kökü yalnız Laravel `public` içeriğini sunar. PHP 8.4 ve gerekli eklentiler, writable `storage`/`bootstrap/cache`, sembolik bağlantı desteği, cron sınırları ve HTTPS hostingde doğrulanmalıdır. Genel `777` izinleri verilmez.

**Mevcut fiziksel uygulama dizinlerini korumak zorunludur:** `drAW`, `GeoMatik2D`, `GeoMatik3D`, `avCELL`. Yayın dizinlerini taşımak, silmek, içeriklerini değiştirmek veya subdomainlere almak bu projenin kapsamı değildir. Yeni Laravel public dizini kullanılacaksa bu dizinlerin mevcut URL'lerden servis edilmesi hostingin desteklediği güvenli eşleme ile sürdürülmelidir. Hosting bu eşlemeyi sağlayamıyorsa mevcut document root korunarak yalnız gerekli Laravel public dosyalarının seçici yerleşimi ve özel uygulama köküne bootstrap bağlantısı planlanır; Laravel `public_path`/Vite/storage yolları bu yerleşimle tutarlı olmalıdır. Nihai seçenek mevcut hosting görülmeden uygulanmaz.

Tam public kökünü temizlemek, bağımsız dizinleri kapsayan `rm`, `rsync --delete` veya silerek yeniden kopyalama kullanılmaz. Release paketi bağımsız uygulama kaynaklarını içermez. `aWa` subdomaini, dosyaları, DB'si ve kullanıcıları yayın hedefinin dışındadır.

Laravel Apache `.htaccess` dosyasının front controller kuralı fiziksel dosya/dizin kontrollerini korur:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

Bu parça tam `.htaccess` dosyasının yerine geçmez; Laravel'in yetkilendirme başlığı ve slash kurallarıyla birlikte kullanılır. Bağımsız dizinlerin kendi `.htaccess` ve giriş dosyaları değişmeden kalır. Çalışan sunucuda `AllowOverride`, `mod_rewrite` ve dizin indeks davranışı onaylı incelemede doğrulanır.

Nginx için eşdeğer temel davranış:

```nginx
root /home/ACCOUNT/public_html;
index index.html index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ /\.(?!well-known).* {
    deny all;
}
```

Buradaki root, bağımsız fiziksel dizinleri aynı URL altında gerçekten sunan kök olmalıdır. PHP-FPM soketi/handler ve Laravel bootstrap yolu hosting yapılandırmasına göre ayrıca belirlenir. Örnek, mevcut server block'u körlemesine değiştirmek için kullanılmaz. Fiziksel uygulama dizini bulunduğunda istek Laravel catch-all'a düşmemelidir. URL büyük/küçük harfi kullanıcı talimatıyla uyumludur; gerçek yayın yazımı onaylı canlı incelemede tekrar doğrulanır.

## Onaylı yayın ve geri dönüş sırası

1. Önce Aşama 4 kapsamlı test onayı alınır, yerel testler tamamlanır ve bulgular giderilir. Sonuç raporu canlı yayın izni sayılmaz.
2. Ayrı canlı yayın onayı sonrası hosting, bağımsız uygulama URL/dizinleri ve yeni DB hedefi salt okunur olarak doğrulanır; dosya yerleşimi kesinleştirilir.
3. Yalnız yeni mADEMatik DB'si, yüklenen medya, özel `.env` ve değiştirilecek ana site giriş/yapılandırma dosyaları yedeklenir. Anahtarlar yedekten geri yüklenebilir olmalıdır; yedek web'den erişilemez ve erişimi sınırlıdır. Mevcut bağımsız public dizinlerin korunmuş konum ve izinleri kayda alınır.
4. Bağımlılıklar kilit dosyalarından hazırlanır: `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`. Derlenmiş `public/build` ve marka varlıkları release'e dahil edilir; Node üretimde çalışmak zorunda değildir.
5. Yeni üretim `.env` değerleri girilir, yeni veritabanı sahipliği doğrulanır ve yalnız bu DB için `php artisan migrate --force` uygulanır. İlk seed gerektiğinde ayrıca değerlendirilir; mevcut içerikleri sıfırlayan seed çalıştırılmaz.
6. Güvenli Super User hesabı etkileşimli komutla oluşturulur. `php artisan storage:link` yalnız doğru public root'a bağlanır. `config:cache`, `route:cache`, `view:cache` uygulanır; cache sonrası hedef DB, mail ve URL yeniden doğrulanır.
7. Seçici dosya geçişi yapılır; cron/worker etkinleştirilir. HTTPS/oturum/SEO ve gerçek `/drAW`, `/GeoMatik2D`, `/GeoMatik3D`, `/avCELL` adresleri ile aWa'nın değişmeden erişimi kontrol edilir. Kısa bakım gerekiyorsa bağımsız uygulamaları kesintiye uğratmayacak kapsam seçilir.
8. Sorun varsa yeni ana site release'i ve onun yapılandırması önceki ana site girişine geri alınır; yeni DB'ye yazılan veriler korunur. Yalnız yeni mADEMatik DB yedeği ve medya gerekirse kontrollü geri yüklenir. Bağımsız uygulamalar ve aWa geri dönüş işleminin dışında kalır. Yeni sürüm verileri varsa yedek üzerine geri yüklemeden önce ayrıca kopyası alınır.

Canlı onay öncesi hiçbir adım gerçek sunucu üzerinde uygulanmaz. Hostingin desteklediği deploy düzeni, SMTP ve hukukî metinlerin nihai kabulü açık kalan yayın ön koşullarıdır.
