# Aşama 4 — kapsamlı test planı ve uygulama durumu

Kullanıcı “commit+push dan sonra kapsamlı testlere başla” diyerek yerel kapsamlı testlere onay verdi. Başlangıç uygulaması `bbaa679` commit'i ile `main` dalına push edildikten sonra testler çalıştırıldı. Ölçülen sonuçlar ve giderilen bulgular [test raporundadır](test-results.md); aşağıdaki tablo kapsamın başvuru planıdır, her satırın bütün olası varyantlarının çalıştırıldığı iddiası değildir.

[AGENTS.md, Aşama 4](../AGENTS.md) gereği testler açık onaydan sonra başladı. Aynı talimatta **“Kapsamlı test onayı canlı yayın onayı değildir.”** denir. Canlı sunucuda inceleme veya dağıtım yapılmadı; bu işlemler için ayrıca onay gerekir.

## Yerel uygulama sonucu

PHPUnit 96 test/926 assertion, Node sayaç testleri 10/10, Chromium kontrolleri 216/216, özel QA MariaDB kontrolleri 12/12 ve Apache fiziksel dizin fixture kontrolleri 17/17 geçti. Tarayıcı kontrolleri açık/koyu temaları ve kamu sayfalarında 320–1920 px genişlikleri kapsar; kontrol edilen ekranlarda axe ihlali ve JavaScript hatası bulunmadı. Gerçek SMTP, canlı hosting ve bağımsız uygulamaların üretim adresleri bu sonuçlara dahil değildir.

## Ortam ve sınırlar

Testlerde yalnız yeni mADEMatik proje alanı ve sahipliği doğrulanmış yeni test DB kullanılır. İşlevsel test veritabanı mevcut geliştirme verisinden ayrı hazırlanır; gerçek kullanıcı verileri alınmaz. Sahipliği doğrulanmamış DB üzerinde migration, temizleme veya seed uygulanmaz. `.env`, APP_KEY, parolalar ve SMTP sırları rapora konmaz.

Mail Laravel fake/log veya açıkça onaylanmış test SMTP kullanır. Üretim SMTP'si ve gerçek e-posta alıcıları kullanılmaz. Diğer Belgeler projelerinde test/kurulum/derleme yapılmaz. aWa bağlantısı, kullanıcıları ve DB'si kapsam dışındadır.

Testler tekrarlanabilir senaryolarla yürütülür. Önce sunucu tarafı işlev/yetki akışları, ardından tarayıcı ve görünüm kontrolleri yapılır. Hatalar yeni uygulamada düzeltilir; yalnız ilgili senaryolar ve etkilenebilecek akışlar tekrar çalıştırılır. Test çıktıları sürüm, tarih, ortam, beklenen/gerçek sonuç ve giderilen bulgularla kısa raporlanır.

## Kontrol kapsamı

| Alan | Senaryolar ve beklenen sonuç |
| --- | --- |
| Temel çalışma | Kurulum/migration MariaDB uyumluluğu, Vite production build, route ve view cache; bütün kamu sayfaları, hata ekranları ve boş durumlar çalışır. |
| Kayıt ve oturum | Geçerli/geçersiz kayıt, aynı e-posta, güçlü parola, kabul metni, login/logout, hatalı giriş limiti, session yenileme; kayıt rolü yalnız Üye. |
| E-posta doğrulama | İmzalı doğrulama bağlantısı, süresi dolan/başka kullanıcıya ait link, tekrar gönderim limiti; doğrulanmadan yorum/yanıt reddedilir. |
| Parola/profil | Reset token ve süre kontrolü, parola değişimi, e-posta değişiminde yeniden doğrulama, profil ve bildirim tercihleri; parola/sırlar response veya logda açığa çıkmaz. |
| Super User yetkisi | Ziyaretçi/Üye/doğrulanmamış üye `/admin` ve bütün yazma endpoint'lerine erişemez; forged role, mass assignment, ID değiştirme yetki yükseltmez. CLI sabit hesap/parola üretmez. |
| Genel/proje yorumları | Her yorum ve her yanıt pending başlar; approved-only public sorgu; pending/rejected/hidden görünmez; üye kendi durumunu görür. Düzenlenen approved yorum pending olur ve önceki onay temizlenir. |
| Yorum ilişki/güvenliği | Başka üyeye ait içerik düzenlenemez; yanıtın proje ve parent ilişkisi geçerlidir; gizli ebeveyn içeriği/yanıtları sızmaz. Uzun içerik, XSS, rate limit, engelli üye ve şikâyet akışları doğrulanır. |
| Moderasyon | Onayla/reddet/gizle/sil, filtreleme ve işlem kaydı; onaylayan/durum/tarih tutarlı; yeni içerik yöneticiye, karar üyeye bildirilir. |
| Bildirimler | Zil/sayfa okunmamış sayısı, kendi bildirimi, okundu/okunmadı işlemleri; başka kullanıcının bildirimi okunamaz. Onaylanan yanıt bildirimi doğru üyeye gider. Tercihler duyuru/yorum mailini yönetir; kritik hesap maili kapanmaz. |
| İletişim | Girişsiz geçerli gönderim, doğrulama/honeypot/rate limit, XSS, yönetici bildirim ve mesaj durumları; adres boşken uygun arayüz davranışı; başarı mesajı SMTP başarısını yanlış iddia etmez. |
| Mail/kuyruk | TR verify/reset/iletişim/yorum şablonları, linkler ve branding; non-production SMTP engeli; kuyruklu job/afterCommit, başarısız job ve güvenli retry; cron kurulu olmadan teslim edilmiş kabul edilmez. |
| Proje/kategori CRUD | Oluştur/güncelle/sırala/öne çıkar/yayın durumu/sil, benzersiz slug, kategori ilişkisi, teknolojiler, arama/filtre/sayfalama, dış bağlantı doğrulaması. Boş EN çeviriler yanlış dilde yayınlanmaz. |
| Medya | İzinli MIME/uzantı/boyut, sahte dosya ve yürütülebilir içerik reddi; dosya yolu traversal; kapak/sıralama/alt metin; silinen medya erişimi ve kayıt tutarlılığı; bağımsız uygulama dizinlerine yazılamaz. |
| Site yönetimi | Menü, üç slider, Hakkımda/ana sayfa içerikleri, SEO, kurumsal/sosyal bilgileri panelden değişir; Türkçe/JSON veri yapısı tutarlı; zararlı dış URL ve HTML reddedilir/escape edilir. |
| Üyeler/veri talepleri | Üye engelleme/geri alma, doğrulanmışlık, kendi hesabına ve son yöneticiye yönelik koruma; hesap/veri silme talebi ve ayrı yönetici onayı. Hesap silme cascade ile yorumları ve başka üyelerce yazılmış bağlı yanıtları da kalıcı siler; anonimleştirme yapılmaz. Bildirim/tercih/oturum/reset kaydı temizliği ve ayrı iletişim/audit verilerinin değerlendirilmesi doğrulanır. Okul yöneticisi/moderatör/editör rolü yoktur. |
| Saat & Sayaç | Gerçek zaman, start/pause/resume/reset, sekmeyi kapat/aç/yenile, bitiş sonrasında doğru sonuç, farklı sekme, saat değişimi; büyüt/küçült ve SAAT/SAYAÇ/ANA SAYFA kontrolleri; tarayıcı depolaması kapalıysa hata yönetimi. |
| TR/EN altyapısı | Ziyaretçi ve admin Türkçe, validasyon ve mail metinleri Türkçe; locale/fallback/JSON stratejisi; `/en` çevirisiz sayfa ve dil seçici yayında değildir; yorum metni otomatik çevrilmez. |
| Görünüm/tema | Mobil/tablet/masaüstü; menü, slider, form, tablo, modal ve hata ekranları; ilk açılış OS teması, kalıcı seçim, tema parlaması, açık/koyu logo yazısı ve değişmeyen kanonik MS. |
| Erişilebilirlik | Klavye/focus sırası, form label/error ilişkisi, modal focus, Escape, ekran okuyucu isimleri, contrast, zoom, `prefers-reduced-motion`; slider düğmeleri ve varsa durdurma kontrolü. |
| SEO/görseller | Başlık/meta/canonical/OG, sitemap yalnız yayındaki URL'ler, robots, slug, güvenli dış link; boyut/alt/picture/srcset/lazy; ilk hero yüklemesi lazy değildir; raster dosyalar SVG diye sunulmaz. |
| Güvenlik | CSRF, SQL injection, XSS escape, IDOR, oturum cookie/HTTPS, authorization her endpoint; upload, zararlı redirect/link, throttling, gizli dosya erişimi, yönetici parola doğrulama; debug kapalı üretim ayarı. |
| Performans | Örnek veriyle sorgu/N+1 ve sayfalama; Vite/font/görsel boyutları, ağ waterfall ve temel Lighthouse kontrolleri; gerçek sunucuya yük testi yapılmaz. |
| Fiziksel uygulamalar | Yerel yeni fixture dizinleriyle Apache fiziksel `-f`/`-d` önceliği ve Laravel fallback davranışı; `/drAW`, `/drAW2D`, `/drAW3D`, `/awCELL` asset/index erişimi bozulmaz. Kaynak uygulamalar kopyalanmaz/değiştirilmez. Canlı web sunucusu farklıysa onun kuralları ayrıca onaylı yayın hazırlığında doğrulanır. |

## Kabul ve açık kalan canlı kontroller

Kritik yetki, veri kaybı, zorunlu yorum onayı, secret sızıntısı veya bağımsız uygulama çakışması bulgusu kapanmadan üretime hazır kabul edilmez. Çalışan yerel sürüm ve ölçülen test sonuçları raporlanır; yürütülmeyen bir kontrol geçmiş sayılmaz.

Gerçek cPanel/PHP-FPM/document root, bağımsız uygulama URL'lerinin harf düzeni, üretim fiziksel dizinleri ve permissions, gerçek SMTP/cron, DNS/SSL ve yeni üretim DB sahipliği yalnız ayrıca onaylanmış canlı yayın hazırlığında doğrulanabilir. Yerel fixture testi mevcut canlı hostingin doğru yapılandırıldığını kanıtlamaz.

Hukukî taslakların nihai kabulü ve yönetilebilir iletişim/profil içeriklerinin doğruluğu kullanıcı tarafından tamamlanmalıdır. Bu kontroller test raporunda yayın ön koşulu olarak açıkça belirtilir. Yayın ve geri dönüş adımları [operasyon belgesindedir](operations.md).
