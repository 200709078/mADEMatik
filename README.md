# mADEMatik Solutions

madematik.com için sıfırdan geliştirilen Laravel 13 uygulaması. Uygulama doğrudan bu klasörün kökündedir; geliştirme sınırları ve dört aşamalı çalışma akışı [AGENTS.md](AGENTS.md) dosyasındadır.

Blade, Tailwind CSS 4, Alpine.js, Inter ve Lucide kullanılır. İlk sürüm Türkçedir; çeviri dosyaları ve JSON içerik alanları TR/EN için hazırlanır. İngilizce içerik yayına hazır olmadan dil seçici ve İngilizce sayfalar açılmaz.

## Yerel çalıştırma

PHP 8.4 ve gerekli Laravel/MySQL eklentileri, Composer, Node/npm ve MariaDB gerekir. Aşağıdaki standart komutlar PATH üzerinden çalışır; projeye ait indirme/bağımlılık önbellekleri `.local/` altında tutulur.

Mevcut yerel kurulum `.env` dosyasını ve ayrı veritabanını hazırlar. `.env` dosyasını paylaşmayın, depoya eklemeyin veya mevcut başka projeden kopyalamayın.

```bash
./scripts/dev-database.sh start
./scripts/dev-database.sh status
php artisan serve --host=127.0.0.1 --port=8000
```

Site: `http://127.0.0.1:8000`. Vite geliştirme sunucusu istenirse ayrı terminalde `npm run dev` çalıştırılır. Derlenmiş varlıklar için:

```bash
npm install
npm run build
```

Yeni ve yalnızca bu uygulama için hazırlanmış veritabanında ilk kurulum:

```bash
php artisan migrate --seed
php artisan madematik:create-super-user
```

Migration komutu yalnızca yeni mADEMatik veritabanına yönelmelidir. Güvenlik denetimini devre dışı bırakmayın. Seed başlangıç site ayarlarını ve salt okunur referanslarla doğrulanan proje tanıtımlarını oluşturur; otomatik yönetici veya sabit parola oluşturmaz. Super User komutu etkileşimli olarak güçlü bir parola ister.

Yerel veritabanını durdurmak için:

```bash
./scripts/dev-database.sh stop
```

Veritabanı MariaDB/MySQL uyumludur. Yerel sunucu yalnızca `storage/runtime/mariadb.sock` Unix soketinden erişilen özel MariaDB örneğidir; TCP ağı kapalıdır. Verisi `storage/runtime/mariadb/` altındadır. `madematik_v2` veritabanı ve ayrı `madematik_v2_user` yalnızca bu projeye aittir. `.env` içindeki `DB_OWNERSHIP=madematik_v2` sahiplik işareti ve tam proje soketi migration güvenlik kontrolünde doğrulanır. Parola `.env` içindedir; runtime klasörünü silmek veri kaybına neden olur.

Veri dizini gerçekten yeni ve boşsa başlangıç betiği MariaDB sistem tablolarını oluşturur ve `php artisan madematik:initialize-local-db` ile yalnız uygulamanın yeni DB/kullanıcısını hazırlar. Var olan dolu dizini tekrar kurmaz. PID ve özel veri/soket yolları doğrulanmadan çalışan bir süreç durdurulmaz.

## Sayfalar ve yönetim

Ana sayfa, Hakkımda, Projelerim ve detayları, Yorumlar, Saat & Sayaç, İletişim, üyelik/profil/bildirim sayfaları ve yalnızca Super User erişimine açık `/admin` bulunur. Yönetim panelinden proje, kategori, medya, slider, sayfa/site ayarları, yorum, üye ve iletişim yönetimi yapılır.

Yeni yorum, yanıt ve düzenleme `pending` olarak kaydedilir. Yalnızca Super User onayından geçen içerik halka görünür. Yorum yazmak için doğrulanmış üyelik gerekir. İletişim adresi başlangıçta boş bırakılır; yönetici gerçek kurumsal adresi site ayarlarından girer. Gizlilik, üyelik ve aydınlatma metinleri hukukî onay bekleyen taslaklardır.

Hesap/veri silme talebi profilden yöneticiye iletilir. Yönetici ayrıca onaylayarak hesabı sildiğinde üyeye ait yorumlar ve bunlara bağlı yanıtlar, bildirimler, tercihler, oturumlar ve parola sıfırlama kaydı kalıcı silinir; yorumlar anonimleştirilmez. Bağlı yanıtlar başka üyeler tarafından yazılmış olsa da silinir. Ayrı iletişim mesajları ve işlem kayıtları saklama koşullarına göre ayrıca değerlendirilir.

Tema işletim sistemi tercihinden başlar ve kullanıcının seçimini tarayıcıda saklar. Saat & Sayaç gerçek yerel zamanı gösterir; geri sayımın başlangıç/bitiş bilgileri ve duraklama durumu tarayıcıda kalıcıdır.

## Görseller, mail ve bağımsız uygulamalar

Onaylı raster marka paketi `public/assets/brand/` altında kullanılır. MS sembolü yeniden çizilmez. Kaynak dosya bilgileri ve SHA256 doğrulaması [referans notlarında](docs/reference-notes.md) bulunur.

Yerelde mail `log`, kuyruk `sync` kullanır. Üretim dışı ortamlarda gerçek SMTP gönderimi uygulama tarafından engellenir. Üretim SMTP, kuyruk/cron ve barındırma kurulumu [operasyon belgesinde](docs/operations.md) açıklanır.

`/drAW`, `/GeoMatik2D`, `/GeoMatik3D`, `/avCELL` mevcut fiziksel yayın dizinleri korunur. aWa bağımsız olarak `https://awa.madematik.com` üzerinde çalışır; bu uygulama yalnızca bağlantı verir. Ortak oturum veya çapraz veritabanı bağlantısı kurulmaz.

## Testler ve yayın durumu

İlk üç aşamadan sonra kullanıcı commit/push yapılmasını ve kapsamlı testlere başlanmasını onayladı. Yerel kapsamlı test sonuçları, giderilen bulgular ve doğrulanmamış canlı koşullar [test raporunda](docs/test-results.md) bulunur. Canlı sunucuya dağıtım yapılmadı; yayın için ayrıca açık onay gerekir.

Hızlı ve bağımsız testler:

```bash
vendor/bin/phpunit
npm run test:clock
```

PHPUnit her testte yeni SQLite `:memory:` şeması oluşturur; geliştirme verisini temizlemez. Mail fake/array ile kontrol edilir. `migrate:fresh` veya `db:wipe` kullanılmaz. Sayaç testleri tarayıcı depolaması, geçersiz süre ve zaman değişimi davranışlarını kapsar.

MariaDB, tarayıcı ve fiziksel uygulama yönlendirme kontrolleri için [ayrı QA ortamı](docs/qa-environment.md) kullanılır. QA hesapları yalnız sahte `.test` adresleri ve rastgele parolalar içerir; gizli dosyaları paylaşmayın. Tarayıcı kontrolleri yalnız localhost'a bağlanır, diğer uygulamaların bağlantılarını ziyaret etmez. Sistem Chromium ve Apache/PHP modülü yerel geniş kontroller için gerekir; bu araçların kurulumu başka projelerde yapılmaz. Vite derlemesini tarayıcı testlerinden önce tamamlayın, aynı anda çalıştırmayın.

Üretimde cPanel hesap öneki taşıyan yeni DB adı kullanılabilir (`account_madematik_v2` gibi). `DB_OWNERSHIP`, gerçekten bu proje için yeni oluşturulan DB adıyla birebir aynı olmalıdır; bu işaret mevcut bir veritabanını sahiplenmek için kullanılmaz. Yerelde tam proje soketi ayrıca doğrulanır.
