# Yerel çalışma ve canlı yayın hazırlığı

Bu belge bir işletim planıdır. Üretim sunucusu, domain, DNS, cPanel, SSL ve mevcut bağımsız uygulamalarda hiçbir işlem yapılmadı. Canlı ortama uygulanacak bütün değişiklikler ayrıca açık kullanıcı onayı gerektirir.

## Yeni uygulamanın veritabanı

Güncel yerel bağlantı sistem MariaDB sunucusundadır:

- TCP adresi: `127.0.0.1:3306`; `.env` içinde `DB_SOCKET` boştur ve `APP_ENV=local` korunur.
- Ayrı veritabanı: `madematik_v2`; yeni hesap: `madematik_v2_user@127.0.0.1`.
- Hesabın yetkisi yalnız `madematik_v2.*` üzerindedir; aWa, `madematik_com` ve diğer mevcut uygulama veritabanları/kullanıcıları değiştirilmedi.
- Kimlik bilgileri yalnız `.env` ve erişimi sınırlı yerel yedeklerde kalır. Runtime, `.env`, yüklenen medya ve yedekler sürüm kontrolüne alınmaz.

13 Ekim 2026 taşımasında 19 InnoDB tablosunun tamamı kaynak/hedef satır sayıları ve SHA256 içerik özetiyle eşit bulundu. `migrate:status` dört migration için `Ran` verdi. Geçici array oturum/önbellek kullanan salt okunur HTTP kontrolü ana sayfada 200 aldı; DB oturum/önbellek verileri değişmedi. Sistem sunucusuna bağlanan Adminer'da yeni veritabanı ve 19 tablo görüldü. Sistemde `migrate:fresh`, `db:wipe` veya `DROP` çalıştırılmadı.

Kullanıcı onaylı dar TCP istisnası `DatabaseSafety` içinde yalnız `APP_ENV=local`, boş soket, tam `127.0.0.1:3306`, `madematik_v2` ve `madematik_v2_user` için geçerlidir. `DB_OWNERSHIP` eşleşmesi, root hesap yasağı ve `DB_URL`/ayrı `read`-`write` hedef korumaları sürer. `LocalDatabase`, sahiplik denetiminden sonra TCP bağlantısında özel örneği başlatmadan çıkar; sistem servisini başlatmaz, durdurmaz veya yapılandırmaz. Sistem MariaDB servisi uygulama açılmadan önce çalışıyor olmalıdır. Özel/QA soket denetimleri ve yıkıcı komut yasakları korunmuştur.

Eski kaynak örnek `storage/runtime/mariadb/` veri dizini ve `storage/runtime/mariadb.sock` üzerinden `--skip-networking` kullanır. Süreç 13 Ekim 2026'da taşıma doğrulandıktan sonra açık kullanıcı onayıyla durduruldu; veri dizini ve yedekler korundu. `scripts/dev-database.sh` yalnız bu eski özel örneği yönetir, sistem MariaDB servisini veya [ayrı QA ortamını](qa-environment.md) yönetmez. Eski soket yeniden seçilirse mevcut otomatik özel örnek başlatma davranışı devam eder; betik PID/yürütücü/veri/soket yollarını doğrulamadan süreç durdurmaz. Dolu dizinde ilk kurulum komutu tekrar çalıştırılmaz.

Eski kaynak artık taşıma anındaki kopyadır. Hedefe sonradan yazılan verileri kaybetmemek için geri dönüşten önce güncel hedef ve medya yedeklenir, farklılıklar değerlendirilir ve açık onay alınır. `.env` otomatik eski sokete çevrilmez; eski kaynağın güncel hedef üzerine geri yüklenmesi veya hedefin silinmesi bu planın otomatik adımı değildir.

`php artisan migrate --seed` yalnız yeni, bu projeye ait boş veritabanında ilk kurulum içindir; taşınmış mevcut veritabanında tekrarlanmaz. Seed gerçek kullanıcı/admin hesapları eklemez. Yeni yönetici `php artisan madematik:create-super-user` etkileşimli komutuyla oluşturulur; otomatik veya raporda yazılı parola yoktur.

Yerel Adminer `http://localhost:8080/adminer.php` adresinden açılır. Sistem MySQL/MariaDB, sunucu `127.0.0.1`, veritabanı `madematik_v2` seçilir; kullanıcı/parola `.env` içindeki `DB_USERNAME`/`DB_PASSWORD` alanlarından alınır ve paylaşılmaz. TCP için özel soket veya `ext=pdo` gerekmez. Proje hesabının diğer uygulama veritabanlarında yetkisi yoktur.

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
| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `3306` | Hosting bağlantısına göre |
| `DB_SOCKET` | Boş; TCP kullanılır | Hosting bağlantısına göre |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `madematik_v2`, ayrı proje hesabı ve gizli parola | Yeni DB ve sınırlı ayrı kullanıcı |
| `DB_OWNERSHIP` | `madematik_v2` sahiplik işareti | Yeni DB hedefi onaylı kurulumda doğrulanır |
| `MAIL_MAILER` | `log` | Onaylı gerçek SMTP |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` | Gerçek SMTP kullanılmaz | Hosting/SMTP sağlayıcısının değerleri |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Yerel/test gönderici | Doğrulanmış kurumsal gönderici |
| `QUEUE_CONNECTION` | `sync` | `database` ve çalışan cron/worker |
| `SESSION_SECURE_COOKIE` | HTTP geliştirmeye uygun | HTTPS için `true` |

Üretim dışı ortamlar gerçek mail gönderemez; uygulama log mailer'a yönlendirir. Mail logları mesaj ve bağlantı içerebilir; yalnız geliştirme kullanıcılarıyla çalışılır ve log erişimi kısıtlanır. Hesap doğrulama/parola sıfırlama kritik mesajları isteğe bağlı proje duyurularından ayrı tutulur.

Kurumsal iletişim adresi ve sosyal bağlantılar başlangıçta boş olabilir; yönetici gerçek bilgileri panelden girmelidir. Varsayımsal adres veya kişisel biyografi üretim bilgisi olarak kullanılmaz. Hukukî metinler taslaktır ve yayın öncesi kullanıcı tarafından gözden geçirilmelidir.

## Profil fotoğrafı ve şema

`users.avatar_path` nullable alanı kullanıcının isteğiyle özgün `0001_01_01_000000_create_users_table.php` migration'ına eklenmiştir; yeni bir ekleme migration'ı yoktur. Başlangıç migration'ı uygulanmış veritabanında dosyayı düzenlemek alanı kendiliğinden oluşturmaz. Mevcut proje yerel veritabanı, özel soket/veri dizini/sahiplik doğrulaması ve özel kullanıcı yedeği ardından yalnız bu nullable alan eklenerek uyumlu hale getirildi. Önceki kullanıcı alanları işlem öncesi/sonrası karşılaştırılarak korundu; sıfırlama, tekrar seed veya migration geçmişi değişimi yapılmadı.

Fotoğraflar `storage/app/public/avatars/` altında rastgele UUID adlarıyla WebP olarak saklanır; doğru `public/storage` bağlantısı gereklidir. Yalnız JPG/PNG/WebP raster girdileri kabul edilir; 2 MB, 4096 piksel kenar ve 12 megapiksel sınırları uygulanır. Yeniden kodlama metadata ve dosyaya eklenmiş içeriği kaldırır. Kullanıcı yalnız kendi fotoğrafını yönetebilir; dosya yolu istekten alınmaz. Değiştirme/kaldırma ve hesap silme sırasında önce DB işlemi tamamlanır, ardından eski fotoğraf temizlenir; DB başarısızlığında mevcut fotoğraf korunur.

Onaylı hosting hazırlığında GD ve WebP kodlama desteği, yazılabilir public disk ve storage bağlantısı doğrulanır. `upload_max_filesize` en az 2 MB, `post_max_size` form ek yükünü karşılayacak kadar büyük olmalıdır. `memory_limit`, 12 megapiksel girdinin çözülmesi ve JPEG yön düzeltmesinin eşzamanlı görüntü tamponları için değerlendirilir. EXIF eklentisi varsa telefon JPEG'lerinin yönü düzeltilir; bu destek yoksa yön bilgisi uygulanmaz. Mevcut hosting yapılandırması bu çalışma kapsamında değiştirilmedi.

## Hesap ve kişisel veri silme

Profildeki silme talebi mevcut parola ve ayrı onay kutusuyla yöneticiye iletilir; talep hesabı hemen silmez. Super User talebi ve saklama koşullarını değerlendirir, üye ekranındaki ayrıca onaylanan kalıcı silme işlemini uygular. Super User hesapları bu ekrandan silinemez.

Mevcut uygulama anonimleştirme yapmaz: kullanıcı hesabı silindiğinde ona ait yorumlar ve bu yorumlara bağlı yanıtlar foreign key cascade ile kalıcı silinir. Bağlı yanıtların başka üyeler tarafından yazılmış olması bu sonucu değiştirmez. Yorum şikâyetleri ve bildirim tercihleri de ilişkilerine göre silinir; kullanıcıya ait site bildirimleri, oturumlar ve parola sıfırlama kaydı açıkça temizlenir. Profil fotoğrafı DB işlemi başarıyla tamamlandıktan sonra public diskten kaldırılır. Ayrı iletişim mesajları ve işlem kayıtları otomatik tam kişisel veri temizliği sayılmaz; yönetici bunlardaki bilgileri ve saklama yükümlülüklerini ayrıca değerlendirmelidir. Yönetici onayından önce bu silme kapsamı gözden geçirilmelidir.

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

Üretimde cPanel hesap öneki taşıyan yeni DB adı kullanılabilir (`account_madematik_v2` gibi). `DB_OWNERSHIP`, gerçekten bu proje için yeni oluşturulan DB adıyla birebir aynı olmalıdır; bu işaret mevcut bir veritabanını sahiplenmek için kullanılmaz. Yerelde proje/QA soketi veya onaylı dar TCP koşulları ayrıca doğrulanır.

DB koruması komutun gerçek `--database` seçimini de kontrol eder; `migrate:install` ve `schema:dump` aynı sahiplik kapsamındadır. Bağlantı hedefini gizlice değiştirebilecek `DB_URL` ve ayrı `read`/`write` bağlantıları desteklenmez. Parçalı `DB_*` ayarlarıyla tek, açık proje bağlantısı kullanılır. Test ortamında da yıkıcı komut engeli aktiftir.

Vite derlemesi yayınlanan dizin üzerinde çalıştırılmaz. Derleme özel release alanında tamamlanır; yeni hash taşıyan varlıklar önce yüklenir, `manifest.json` aynı dosya sisteminde atomik yeniden adlandırmayla en son değiştirilir. Önceki varlıklar önbellekteki sayfalar için tutulur; geri dönüşte önceki manifest kullanılabilir. Ana public kökü veya bağımsız uygulama dizinleri silinmez. Yerel QA sırasında eşzamanlı build'in manifesti geçici kaldırabildiği gözlendiği için test koşusu ile build sırayla yürütülmelidir.
