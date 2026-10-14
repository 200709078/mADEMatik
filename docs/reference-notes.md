# Salt okunur referans incelemesi

İnceleme tarihi: 11 Ekim 2026. Diğer proje klasörlerinde hiçbir değişiklik, kurulum, test veya veritabanı komutu çalıştırılmadı. `.env`, gerçek kullanıcı/veritabanı kayıtları ve SMTP sırları okunmadı. Eski madematik.com projesi incelenmedi ve hiçbir içerik aktarılmadı. Üretim sunucusuna ağ erişimi yapılmadı.

## Görsel paket

Kaynak: `~/Belgeler/mADEMatik-Gorseller/mADEMatik_Solutions_Gorsel_Paketi.zip`.
Arşiv iç kökü: `madematik-solutions-gorsel-paketi/`.
`KULLANIM.md` ve `manifest.json` okundu. Manifestteki 31 dosyanın SHA256 ve byte boyutları, arşivden salt okunur olarak hesaplandı; bütün değerler eşleşti.

- Kanonik sembol: `sources/kabul-edilen-orijinal-ms.png` (1536×1536).
- Web ikonu: `icons/ms-brand-icon-1024.png` (1024×1024); orijinal şekil ve renkler korunuyor.
- Header logoları: `logos/logo-header-light.png`, `logos/logo-header-dark.png` (810×210).
- Büyük yatay logolar: `logos/logo-horizontal-light.png`, `logos/logo-horizontal-dark.png` ve WebP eşleri (1620×420).
- Masaüstü bannerları: `banners/slider-01-madematik.webp`, `banners/slider-02-projelerim.webp`, `banners/slider-03-egitim-teknoloji.webp` (1600×900).
- Mobil bannerları: aynı adların `-mobile.webp` eşleri (800×450).
- Sosyal görsel: `social/og-madematik-1200x630.png` ve WebP eşi.
- Varsayılan proje kapağı: `placeholders/proje-varsayilan-1200x675.webp`.
- Favicon/Apple/PWA: `icons/favicon.ico`, `icon-16.png`, `icon-32.png`, `icon-180.png`, `icon-192.png`, `icon-512.png`.

Bu paket PNG/WebP/ICO raster kaynaklardan oluşur. MS sembolü değiştirilmemeli; slider yazıları erişilebilir HTML olmalı. `sources/` üretime kopyalanmak zorunda değildir.

## Learn English with ALA yaklaşımı

Salt okunur kaynak proje: `~/Belgeler/aydin_language_academy`.

- `app/Models/Review.php`: `pending`, `approved`, `rejected`, `archived` durumları; SoftDeletes; onaylayan ve onay tarihi alanları.
- `app/Livewire/MyReviews.php`: yeni yorum her zaman `pending`; düzenleme tekrar `pending` ve onay bilgilerini temizliyor; üyeye yalnızca kendi yorumları getiriliyor; RateLimiter ile dakika başına 5 yorum denemesi ve bekleyen yorum sınırı.
- `app/Policies/ReviewPolicy.php`: sahibi olan kullanıcı için sunucu tarafında düzenleme/silme denetimi.
- `app/Http/Controllers/Frontend/ReviewController.php`: halka açık sorgu yalnızca `approved` yorumları getiriyor, eager loading ve sayfalama kullanıyor.
- `app/Http/Controllers/cont_reviews.php`: yönetici onay metadata kaydı ve içerik doğrulaması.
- `app/Notifications/AdminApprovalRequiredNotification.php`: Laravel `MailMessage`, kuyruklu Notification, işlem tamamlandıktan sonra `afterCommit`.
- `app/Services/AdminApprovalNotificationService.php`: onay bekleyen yorum/iletişim için yönetici bilgilendirmesi, alıcı doğrulaması ve hata kaydı.
- `app/Notifications/ContactMessageReplyNotification.php`: dile göre bildirim metni ve e-posta yanıt adresi.
- `app/Jobs/SendContactMessageReply.php`: yeniden deneme/timeout ve gönderim başarısızlık durumunun kaydı.
- `config/mail.php`: SMTP/gönderici bilgileri Laravel çevre değişkenleri üzerinden yapılandırılıyor; hiçbir değer alınmadı.
- `app/Providers/FortifyServiceProvider.php`: Laravel Fortify kayıt/şifre/profil işlemleri ve oturum açmada dakika başına 5 deneme sınırı.

**Uyarlanırken dikkat:** ALA kaynaklarında `emailVerification` ve `MustVerifyEmail` etkin değil. mADEMatik'te doğrulama altyapısı bağımsız olarak geliştirildi; son kullanıcı talimatıyla `EMAIL_VERIFICATION_ENABLED=false` üzerinden geçici kapatıldı, kodları korundu. Kayıtta e-posta adresi iki kez yazılıp eşleşmesi denetlenir; ayar yeniden açıldığında doğrulanmamış hesaplarda doğrulama şartı geri gelir. Şube, puan, ALA rol kapsamı ve Livewire zorunluluğu yeni modele taşınmayacak. Yeni model proje/genel yorumları ve yanıtları kapsayacak; her yanıt ve düzenleme zorunlu onay alacak.

## Bağımsız ürünlerin doğrulanabilen bilgileri

| Ürün | Yerel kaynak | Kaynakla doğrulanan kısa tanıtım | Teknolojiler |
| --- | --- | --- | --- |
| drAW | `~/Belgeler/drAW/package.json`, `src/` | Etkileşimli beyaz tahta ve çizim uygulaması | TypeScript, Vite, Electron |
| GeoMatik2D | `~/Belgeler/aWa_GEO/GeoMatik2D/renderer/index.html` | İki boyutlu grafik, fonksiyon ve matematik/geometri aracı; yardımda dizi, limit, türev, bileşke, teğet ve parçalı fonksiyon komutları var | JavaScript, Canvas, math.js |
| GeoMatik3D | `~/Belgeler/aWa_GEO/GeoMatik3D/package.json`, `src/main.ts` | Üç boyutlu geometri aracı; nokta, doğru, ışın, açı, düzlem, küre, prizma, piramit, silindir ve koni araçları var | TypeScript, Vite, Three.js |
| avCELL | `~/Belgeler/aWa_GEO/avCELL/public/index.html`, `app.js` | Hücre tabanlı çalışma kitabı; formül, biçimlendirme, sıralama, filtre ve grafik araçları var | JavaScript, HTML, CSS |
| aWa | `~/Belgeler/aWa/routes/web.php`, `composer.json`, `package.json` | Okul, öğrenci ve personel; sınav dağıtımı ve oturma planı yönetimi | Laravel, Vue, Inertia |

Kısa açıklamalar yalnızca yerel kaynaklara dayanır; özelliklerin üretimde mevcut sürümle eşleştiği iddia edilmez. Ürün kaynakları ve görselleri yeni projeye kopyalanmadı.

## Adresler ve yayın sınırı

Kullanıcının verdiği korunacak adresler: `https://madematik.com/drAW`, `https://madematik.com/GeoMatik2D`, `https://madematik.com/GeoMatik3D`, `https://madematik.com/avCELL` ve `https://awa.madematik.com`.

Yerel klasörler ve HTML başlıklarında `drAW`, `GeoMatik2D`, `GeoMatik3D`, `avCELL` büyük/küçük harf kullanımları doğrulandı. drAW ve GeoMatik3D Vite tabanı göreli `./` kullanıyor. Tam üretim URL ve mevcut hosting/public dizinleri bu salt okunur yerel inceleme ile bağımsız olarak doğrulanamadı; canlı yayın hazırlığında ayrıca onaylı inceleme gerektirir. URL'ler kullanıcı talimatındaki yazımla korunmalıdır.

aWa bağımsız uygulama/veritabanı/yönetim paneli olarak kalır. Ana site yalnızca ürün tanıtımı ve haricî bağlantı sağlar. SSO, çapraz veritabanı bağlantısı ve aWa hesap işlemi uygulanmaz.
