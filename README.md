# mADEMatik Solutions

madematik.com için sıfırdan geliştirilen Laravel 13 uygulaması. Uygulama doğrudan bu klasörün kökündedir; geliştirme sınırları ve dört aşamalı çalışma akışı [AGENTS.md](AGENTS.md) dosyasındadır.

Blade, Tailwind CSS 4, Alpine.js, Inter ve Lucide kullanılır. İlk sürüm Türkçedir; çeviri dosyaları ve JSON içerik alanları TR/EN için hazırlanır. İngilizce içerik yayına hazır olmadan dil seçici ve İngilizce sayfalar açılmaz.

## Yerel çalıştırma

PHP 8.4 ve gerekli Laravel/MySQL eklentileri, Composer, Node/npm ve MariaDB gerekir. Aşağıdaki standart komutlar PATH üzerinden çalışır; projeye ait indirme/bağımlılık önbellekleri `.local/` altında tutulur.

Mevcut yerel kurulum `.env` dosyasını ve ayrı veritabanını hazırlar. `.env` dosyasını paylaşmayın, depoya eklemeyin veya mevcut başka projeden kopyalamayın.

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Yerel `php artisan serve` ve `composer dev`, web sunucusunu açmadan önce bu projeye ait mevcut MariaDB örneğini otomatik başlatır. Bilgisayar yeniden açıldıktan sonra da aynı komut yeterlidir. Bu davranış yalnız `APP_ENV=local` ve sahipliği doğrulanmış geliştirme soketinde çalışır; QA/test/üretim ortamlarını başlatmaz. Migration, seed, veritabanı oluşturma veya parola değiştirme yapmaz. İlk DB kurulumu henüz yapılmamışsa açık bir hata ile durur.

Veritabanını web sunucusundan ayrı başlatmak veya durumunu görmek için:

```bash
./scripts/dev-database.sh start
./scripts/dev-database.sh status
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

Veritabanı MariaDB/MySQL uyumludur. Yerel sunucu yalnızca `storage/runtime/mariadb.sock` Unix soketinden erişilen özel MariaDB örneğidir; TCP ağı kapalıdır. Sistemin açılışta çalışan MariaDB servisinden bağımsızdır. Web terminali kapandığında özel DB çalışmaya devam eder; bilgisayar kapanınca durur ve sonraki `artisan serve` onu tekrar açar. Verisi `storage/runtime/mariadb/` altındadır. `madematik_v2` veritabanı ve ayrı `madematik_v2_user` yalnızca bu projeye aittir. `.env` içindeki `DB_OWNERSHIP=madematik_v2` sahiplik işareti ve tam proje soketi migration güvenlik kontrolünde doğrulanır. Parola `.env` içindedir; runtime klasörünü silmek veri kaybına neden olur.

Veri dizini gerçekten yeni ve boşsa başlangıç betiği MariaDB sistem tablolarını oluşturur ve `php artisan madematik:initialize-local-db` ile yalnız uygulamanın yeni DB/kullanıcısını hazırlar. Var olan dolu dizini tekrar kurmaz. PID ve özel veri/soket yolları doğrulanmadan çalışan bir süreç durdurulmaz.

## Adminer bağlantısı

Adminer'de yalnız `localhost` yazmak sistemin MariaDB soketini seçer. mADEMatik kullanıcı bilgileri projenin özel MariaDB örneğine aittir. Adminer aynı proje kullanıcısıyla çalışmalıdır; soket dizininin izinleri başka servis kullanıcılarına açılmaz.

Mevcut yerel Adminer için `http://localhost:8080/adminer.php?ext=pdo` açılır. Giriş alanları:

- Sistem: MySQL / MariaDB.
- Sunucu: `localhost:/home/madematik/Belgeler/mADEMatik/storage/runtime/mariadb.sock` (başka yerel proje yolunda tam soket yolunu kullanın).
- Kullanıcı: `.env` içindeki `DB_USERNAME`.
- Parola: `.env` içindeki `DB_PASSWORD`; paylaşılmaz veya depoya yazılmaz.
- Veritabanı: `.env` içindeki `DB_DATABASE`.

Kurulu Adminer 5.2.1'in MySQLi sürücüsü özel soket yolunu doğru aktarmadığından `ext=pdo` seçilir; [ilgili sürümün kaynak kodu](https://raw.githubusercontent.com/vrana/adminer/v5.2.1/adminer/drivers/mysql.inc.php) PDO bağlantısının bu biçimi desteklediğini gösterir. Parola sıfırlama, sistem MariaDB'sine kullanıcı ekleme veya mevcut uygulamaların ayarlarını değiştirme gerekmez. Önce `php artisan serve` ile özel DB hazır olmalıdır.

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
