# İzole QA ortamı

`scripts/prepare-qa.py`, kapsamlı tarayıcı ve MariaDB kontrollerini temiz bir yerel klonda tekrar çalıştırmak için ayrı QA ortamı kurar. Mevcut geliştirme veritabanını, diğer projeleri ve çalışan sistem MariaDB servisini kullanmaz. Canlı sunucuda çalıştırılmaz.

Gereksinimler: Linux `/proc`, Python 3.9+, PHP 8.4 ve gerekli PHP eklentileri, projenin kurulmuş Composer/npm bağımlılıkları, `/usr/sbin/mariadbd`, `/usr/bin/mariadb-install-db`, `/usr/bin/mariadb`; tarayıcı kontrolleri için `/usr/bin/chromium`. Sistem paketlerini yardımcı kurmaz. Normal proje kullanıcısıyla çalıştırın; root ile kurulum reddedilir.

Laravel yapılandırma önbelleği bulunmamalıdır. PHP/Artisan komutlarını QA ortamındaki değerleri değiştirecek `DB_URL`, `DB_*`, `APP_*`, `MAIL_*` ortam değişkenleri tanımlanmamış bir terminalde çalıştırın. Kurulum yardımcısı kendi alt süreçlerinde bu dış değerleri geçirmez.

## İlk kurulum

Proje kökünde çalıştırın:

```bash
npm run build
python3 scripts/prepare-qa.py
php artisan serve --env=qa --host=127.0.0.1 --port=8015
```

Kurulum rastgele `storage/runtime/qa-<8hex>/` dizini, TCP ağı kapalı özel MariaDB, yeni `madematik_v2_qa_<8hex>` veritabanı ve yalnız o DB'ye yetkili ayrı kullanıcı oluşturur. Migration/seed yalnız bu yeni instance üzerinde çalışır. Başka bir DB'ye bağlanmaz; `migrate:fresh` veya `db:wipe` kullanmaz.

Test hesabı parolaları ve anahtarlar rastgele üretilir. `.env.qa`, `.local/qa-context.json` ve `.local/qa-users.json` izinleri `600` olur; Git bunları dışarıda bırakır. Bunları paylaşmayın ve içeriklerini raporlara eklemeyin. QA yardımcı betiği sabit parola kullanmaz. Sahte `example.test` üye ve Super User, seed projeleri, onaylı genel yorum ve sahte iletişim mesajı yalnız QA DB'sindedir. Mailer `log` kullanır; gerçek SMTP gönderimi yapılmaz. Super User doğrulanmış olarak yalnız bu test ortamında oluşturulur.

`.env.qa`, context veya erişim dosyalarından herhangi biri zaten varsa yeni kurulum **reddedilir**. Yarım kalan kurulumda dosya ve veriler korunur; yardımcı bunları otomatik silmez veya sıfırlamaz. Sorunu özel `storage/runtime/qa-<8hex>/setup.log` ve sunucu günlüklerinde yerel olarak inceleyin. Başarısızlıkta yalnız sahipliği doğrulanmış kendi MariaDB sürecini kapatır; zorla sonlandırmaz.

## Kontroller ve yeniden başlatma

QA HTTP sunucusu çalışırken ikinci terminalde:

```bash
QA_FIXTURE_PATH=.local/qa-users.json node scripts/browser-qa.mjs
php scripts/check-qa-runtime.php
```

Tarayıcı raporu ve ekran görüntüleri `storage/runtime/qa/` altında, MariaDB raporu `storage/runtime/qa-runtime-report.json` dosyasındadır. Tarayıcı dış ağ isteklerini engeller. MariaDB çalışma zamanı kontrolü yalnız QA veritabanına ek sahte test kayıtları yazar; normal geliştirme verilerini kullanmaz.

Bağımsız public uygulama klasörlerinin yönlendirme kontrolü ayrı, sahte Apache fixture'ında çalışır:

```bash
python3 scripts/check-public-routing.py
```

Bu kontrol sistem Apache/PHP modülünün mevcut olmasını gerektirir; paket kurmaz. Yalnız proje içindeki geçici fixture ve loopback `8016` portunu kullanır, kontrol bitince kendi Apache sürecini kapatır. Gerçek drAW/drAW2D/drAW3D/awCELL/aWa kaynaklarına veya yayın klasörlerine bağlanmaz.

HTTP sunucusunu kendi terminalinde `Ctrl+C` ile kapatın; ardından:

```bash
python3 scripts/prepare-qa.py --stop
python3 scripts/prepare-qa.py --status
```

Veriler silinmez. Aynı QA ortamını daha sonra açmak için:

```bash
python3 scripts/prepare-qa.py --start
php artisan serve --env=qa --host=127.0.0.1 --port=8015
```

`--start` migration, seed veya fixture işlemlerini tekrarlamaz. `--stop` yalnız QA MariaDB'yi kapatır; Artisan HTTP sunucusunu yönetmez. PID, gerçek yürütülebilir dosya, kullanıcı sahipliği, veri dizini, özel soket ve TCP kapalı süreç parametresi eşleşmeden hiçbir sürece sinyal gönderilmez. Başka bir sürece ait PID veya sahipsiz soket bulunursa işlem durur. Yeni ortam için mevcut QA dosyalarını silmeye yönelik otomatik seçenek bulunmaz.
