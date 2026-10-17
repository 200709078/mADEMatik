# Projelerin indirilebilir sürümleri

Proje detayındaki “İndirilebilir Sürümler” alanı bütün projeler için ortaktır. Bölüm, bağlantısı olan ve yayına açılmış kayıtlar varsa görünür; üstteki “İndir” düğmesi aynı bölümün `#indirmeler` bağlantısına gider. Mevcut bağımsız uygulama bağlantıları ve yayın klasörleri korunur. Ayrı bir genel indirme sayfası eklenmedi.

## Yönetim

`Yönetim → Projelerim → Projeyi Düzenle → İndirilebilir Sürümler` bölümündeki “İndirme Ekle” düğmesi ayrı bir kayıt formu açar. Kayıtlar buradan düzenlenir veya silinir. “Ziyaretçilere göster” işareti kaldırılarak dosyayı ve kaydı silmeden yayından kaldırılır. Proje gizlenirse ona bağlı indirmeler de halka açılmaz.

- Windows, macOS, Linux, Android, iOS ve iPadOS seçenekleri vardır; yeni platform kodları şema değişikliği gerektirmez.
- Doğrudan dosya, mağaza ve deneme bağlantıları desteklenir. Google Play, App Store, Microsoft Store ve TestFlight bağlantılarının düğmeleri uygun adla gösterilir.
- Mimari, paket adı, sürüm numarası, tarih, bayt cinsinden boyut, kurulum notları, sistem gereksinimleri, lisans bağlantısı ve SHA-256 isteğe bağlıdır. Kanal kararlı veya beta olabilir.
- Bir platform için birden çok paket, mimari ve sürüm saklanabilir. Küçük sıralama değeri önce gelir; aynı sıralamada yeni yayın tarihi ve kayıt önce gösterilir. Eski sürümler silinmeden taslak olarak tutulabilir.
- Taslaklarda bağlantı boş olabilir. Yayına açmak için geçerli bir HTTPS bağlantısı veya güvenli `/site-yolu` gerekir. Kullanıcı bilgisi taşıyan adresler ve güvensiz şemalar kabul edilmez.

Dosyalar veritabanına veya Git deposuna eklenmez. Panel, paket yükleme ve uygulama içi otomatik güncelleme hizmeti sunmaz; mevcut paketlerin ve mağazaların bağlantılarını yönetir. Sunucu bu adresleri kaydederken uzaktan çağırmaz. İndirme bağlantıları giriş gerektirmeyen proje sayfasında gösterilir. Kaydı silmek uzak dosyayı silmez.

## drAW için doğrulanmış başlangıç bilgileri

17 Ekim 2026'da yalnız salt okunur incelenen drAW `release/` klasöründe iki gerçek Linux paketi bulundu. Sürüm ve tarih `latest-linux.yml`, DEB mimarisi ve bağımlılıkları paket metadata'sı, dosya boyutları ve SHA-256 değerleri doğrudan paketler üzerinden doğrulandı. Kaynak paketlerin ve drAW projesinin hiçbir dosyası değiştirilmedi veya buraya kopyalanmadı.

| Paket | Sürüm | Mimari | Bayt | Yayın tarihi |
| --- | --- | --- | ---: | --- |
| AppImage | 0.0.3 | x86_64 | 98796802 | 16.10.2026 |
| DEB | 0.0.3 | amd64 | 100367184 | 16.10.2026 |

Kullanıcı mevcut bir indirme adresi olmadığını belirtti. Bu nedenle iki kayıt da **bağlantısız, yayına kapalı taslak** olarak hazırlanır. Windows/macOS/Android/iOS için hazır paket varsayılmadı. Bağlantı eklendikten sonra yönetici kaydı yayına açabilir. MIT lisans dosyasının herkese açık bağlantısı doğrulandı; sürüme özgü diğer veriler `config/project-downloads.php` içindedir.

`madematik:import-draw-downloads` yalnız `draw` slug'lı mevcut projenin eksik paketlerini SHA-256 kimliğiyle ekler. Tekrar çalıştırıldığında yöneticinin mevcut bağlantı, yayın durumu, metin, sıralama veya çeviri düzenlemelerini değiştirmez. `draw` yoksa proje oluşturmaz ve durur. İlk kurulumdaki ana seeder aynı katalogdan yararlanır; mevcut kurulumda tüm seeder yeniden çalıştırılmaz.

## Şema ve onaylı yayın adımı

Site canlı olduğu için eski migration dosyaları değiştirilmedi. Yeni `2026_10_17_000100_create_project_downloads_table.php`, yalnız `project_downloads` tablosunu ve projeye bağlı foreign key'i oluşturur. Üyeler, yorumlar, mevcut projeler ve ayarlar değiştirilmez. Yerelde yalnız sahipliği doğrulanmış mADEMatik DB'sine uygulandı; öncesinde özel SQL yedeği alındı ve bu dört tablonun satır sayıları/içerik özetleri aynı kaldı. İki taslak yerel DB'ye eklendi. Canlıya bu özellik uygulanmadı.

Kullanıcının ayrıca onayladığı canlı güncellemede, yeni mADEMatik DB yedeği ve sahiplik doğrulamasından sonra yalnız yeni migration uygulanır:

```bash
php artisan migrate --force --path=database/migrations/2026_10_17_000100_create_project_downloads_table.php
php artisan madematik:import-draw-downloads
```

Normal yayın hazırlığında Vite varlıkları yeniden derlenir; yeni `public/build` varlıkları ve manifest güvenli biçimde yayımlanır. HTTP ve CLI aynı Laravel/public kökünü kullanmalıdır. `migrate:fresh`, `db:wipe`, eski migration düzenleme veya diğer uygulamaların veritabanları bu işleme dahil değildir. Bu komutlar burada canlıda çalıştırılmadı.

## Doğrulama

Yeni özelliğin odaklı testleri `tests/Feature/ProjectDownloadTest.php` içindedir. SQLite `:memory:` ve sahte e-posta kullanılır; mevcut DB veya gerçek SMTP test hedefi değildir. Testler yönetici yetkileri, projeler arası kayıt erişimi, taslak gizliliği, mobil bağlantılar, XSS escape, URL/metadata doğrulaması, çeviri koruması ve tekrarlanabilir katalog aktarımını kapsar.

27 yeni testte 311 doğrulama; ilgili yönetim, public sayfa ve sitemap sınıflarındaki 40 testte 548 doğrulama geçti. Toplam 67 odaklı test ve 859 doğrulama başarılıdır. PHP sözdizimi, Blade derlemesi, beş yönetici rotası ve Vite üretim derlemesi de doğrulandı. Yeni manifestteki tüm varlıklar mevcuttur; önceki derlemenin izlenen JS/CSS dosyaları önbellekteki sayfalar için korundu.

Sentetik SQLite verisiyle alınan gerçek Blade çıktılarında altı tarayıcı görünümü kontrol edildi: proje sayfası 390/1280 px açık-koyu tema, yönetici ekleme formu 390 px açık tema ve düzenleme formu 1280 px koyu tema. Yatay taşma, kesilen başlık, Alpine/JavaScript hatası ve dış ağ isteği görülmedi. Yayın kutusunun bağlantıyı zorunlu yapması, mobil mağaza düğmeleri, beta/SHA-256 gösterimi ve taslak gizliliği doğrulandı. Gerçek DB ve çalışan yerel HTTP sunucusu bu tarayıcı kontrolünde kullanılmadı. Git dışındaki rapor ve altı ekran görüntüsü `storage/runtime/qa/project-downloads/` altındadır; sentetik HTML ve geçici yardımcılar kaldırıldı.
