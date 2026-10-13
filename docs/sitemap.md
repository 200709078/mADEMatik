# Sitemap ve otomatik yenileme

Site haritası `/sitemap.xml` adresindedir. Yalnız ana sayfa, Hakkımda, Projelerim, Yorumlar, Saat & Sayaç, İletişim ve yayımlanmış proje detayları bulunur. Proje sorgusu yalnız `slug` ve `updated_at` alanlarını okur; kullanıcı, iletişim, oturum, bildirim, yorum içeriği, dosya yolu veya ayar/parola verilerini almaz. Projelerin dış uygulama bağlantıları da sitemap'e aktarılmaz. aWa uygulaması, veritabanı ve okul kayıtları bu işleme dahil değildir.

URL kökü yalnız `APP_URL` ayarından alınır. İsteklerin Host başlığı, sorgu parametreleri, e-posta veya tokenları XML'e eklenmez. Yayından kaldırılan projeler web sitemap'inden hemen çıkar; tarayıcıya eski cron kopyası sunulmaz. Sitemap bir erişim kontrolü değildir: özel sayfalar ayrıca sunucu tarafında yetkilendirilir.

## Yerel üretim ve dosya

```bash
php artisan madematik:generate-sitemap
```

Komut yalnız bu projenin doğrulanmış bağlantısını okur; migration/seed veya DB değişikliği yapmaz. Geçerli XML hazırlandıktan sonra `storage/app/private/seo/sitemap.xml` atomik olarak yenilenir. Dosya izni `0600`, yeni özel dizin izni `0700` olur. Hatalı bağlantı veya URL ayarı önceki başarılı dosyayı bozmaz; komut hata ayrıntıları, SQL veya parola yazdırmaz. Özel dosya web köküne kopyalanmaz ve `public/storage` üzerinden yayımlanmaz.

Herkese açık `/sitemap.xml` aynı üreticinin güncel XML'ini döndürür. Bu nedenle cron henüz kurulmamış olsa da açık adres günceldir. Cron dosyası özel bir düzenli çıktı olarak saklanır; yayından kaldırma durumunda eski URL'nin dışarı açık dosyada kalması önlenir.

## Canlıda cron

Laravel zamanlayıcısında `madematik:generate-sitemap`, yalnız `production` ortamında saatte bir ve çakışma engeliyle tanımlıdır. Yerel/test ortamında otomatik çalışmaz. İlk canlı dosya manuel komutla oluşturulur; sonrasında aşağıdaki **örnek** cPanel cron girdisi Laravel zamanlayıcısını her dakika tetikler:

```cron
* * * * * /usr/local/bin/php /home/ACCOUNT/apps/madematik/artisan schedule:run >> /home/ACCOUNT/apps/madematik/storage/logs/scheduler.log 2>&1
```

PHP yürütücü yolu ve `ACCOUNT`/uygulama yolu, onaylı canlı hazırlığında gerçek hosting değerleriyle değiştirilmelidir. Mevcut `schedule:run` cron'u varsa ikinci bir tane açılmaz. `APP_ENV=production`, doğru HTTPS `APP_URL`, yazılabilir özel storage ve çalışan cache kilidi gereklidir; kuyruk worker'ı sitemap için gerekli değildir. Görevin sıklığı Laravel tarafında saatliktir; cron girdisi dakika başına kontrol yapar. Bu düzen [Laravel 13 zamanlayıcı belgesine](https://github.com/laravel/docs/blob/13.x/scheduling.md) uygundur.

Canlı hazırlığında `php artisan schedule:list`, bir manuel üretim, XML/robots adresleri, özel dosyanın erişilememesi ve sonraki saatlik dosya zamanının yenilenmesi kontrol edilir. Bu çalışma canlı cron'u kurmaz; cPanel/sunucu işlemi ayrıca kullanıcı onayı gerektirir.

## Gizlilik ayarları

Üretimde ayrıntılı hata gösterimi uygulama tarafından zorunlu kapatılır; JSON sunucu hataları genel mesaj döndürür. Hesap/yönetim sayfaları ve giriş yapılmış cevaplar `private, no-store` ve `noindex, nofollow` başlıkları taşır. Parola sıfırlama/doğrulama sayfalarında referrer aktarımı kapalıdır. Kullanıcı modeli array/JSON olarak yalnız açık kimlik alanlarını çıkarır; e-posta, yetki, parola, dosya yolu ve özel ilişkileri taşımaz. Profil ve admin arayüzleri gerekli alanları kendi sunucu tarafı yetkileriyle okumaya devam eder.

Özel storage diski için dosya indirme rotası kapalıdır. `.env`, kaynak kod, günlükler ve yedekler web kökü dışında tutulur; Apache public kuralları `.env` ve temel kaynak dosyalarını ayrıca engeller. `robots.txt` ve noindex yetkilendirmenin yerine geçmez. Üretimde debug kapatma gerekçesi [Laravel 13 yapılandırma belgesinde](https://github.com/laravel/docs/blob/13.x/configuration.md#debug-mode) açıklanır.

Gerçek sunucunun document root, dosya izinleri, HTTPS ve DB yetkileri onaylı canlı hazırlığında ayrıca doğrulanmalıdır. Ana sitede onaylı yorumların yazar adı/avatarı ve yayımlanmış proje içerikleri bilinçli olarak herkese açıktır; öğrenci/veli/öğretmen/personel kayıtları için çapraz uygulama sorgusu bulunmaz.
