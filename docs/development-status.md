# Geliştirme durumu — 12 Ekim 2026

İlk üç geliştirme aşaması tamamlandı. Kullanıcının “commit+push dan sonra kapsamlı testlere başla” talimatı üzerine başlangıç uygulaması `bbaa679` commit'i ile `main` dalına push edildi; ardından Aşama 4 yerel testleri çalıştırıldı ve bulunan sorunlar giderildi. Canlı yayın yapılmadı; bunun için ayrıca açık kullanıcı onayı gerekir.

## Aşama 1

- Laravel 13.35 uygulaması doğrudan proje kökünde; AGENTS.md korundu.
- PHP 8.4 ortamı ve gerekli eklentiler gözlemlendi; sistem veya başka projelere bağımlılık kurulmadı.
- Yalnız bu projeye ait özel MariaDB veri dizini/soketi, yeni `madematik_v2` ve ayrı DB kullanıcısı oluşturuldu. TCP erişimi kapalıdır.
- Nihai başlangıç şeması, Üye/Super User modeli, yetkilendirme, TR/EN JSON içerikleri ve çeviri dosyaları kuruldu.
- Merkezi tokenlar, Inter, Lucide, Blade bileşenleri, frontend/admin layout, işletim sistemi tercihi ve kalıcı açık/koyu tema eklendi.
- Kanonik marka paketi kullanıldı; arşivdeki 31 dosyanın boyutu ve SHA256 değerleri doğrulandı. Kaynak arşiv değişmedi.

## Aşama 2

- Üç manuel kontrollü banner, Hakkımda, filtreli/sayfalı Projelerim ve detaylar, genel/proje yorum arayüzleri, İletişim ve hukukî taslak sayfaları geliştirildi.
- Saat & Sayaç gerçek yerel zamanı, kalıcı başlangıç/bitiş, duraklatma/devam/sıfırlama ve büyütme/küçültmeyi içerir.
- Kayıt/giriş/doğrulama/reset/profil ve bildirim görünümleri eklendi. SEO başlıkları, canonical, OG, sitemap, robots, ikon/manifest ve ortak hata sayfaları hazır.
- Proje tanıtımları yeni içeriklerdir; yalnız kullanıcı talimatları ve salt okunur yerel kaynaklarla doğrulanan bilgiler kullanıldı. Sahte ziyaretçi yorumu, kişi bilgisi veya başarı istatistiği üretilmedi.
- İngilizce kamu sayfası ve dil seçici yayınlanmadı; gelecekte çeviri ve `/en` adres stratejisi uygulanabilir.

## Aşama 3

- Laravel tabanlı oturum, e-posta doğrulama, parola reset/değişim, profil, bildirim tercihleri ve veri silme talebi uygulandı.
- Yeni yorum ve yanıtlar DB varsayılanı ile `pending`; düzenlemeler de yeniden `pending`. Görünür sorgular yalnız onaylı içerikleri ve onaylı ebeveyni gösterir.
- Super User onay/reddet/gizle/sil işlemleri, işlem kayıtları, yorum şikâyeti ve üye engelleme eklendi.
- Yönetim panelinde proje/kategori/medya CRUD, üyeler, iletişim mesajları, bildirimler, site/menü/slider/sayfa/SEO ayarları ve işlem kayıtları hazır.
- Raster yüklemelerde MIME/boyut kontrolü ve WebP yeniden kodlama; CSRF, Policy/Gate, rate limit ve ek yönetici parola doğrulaması kullanılır.
- Site bildirimleri anında DB'ye yazılır. Tercihli mail kuyruklanır; kritik hesap bildirimleri ayrı kalır. Yerelde mail log'a zorlanır ve kuyruk sync'tir; gerçek alıcıya gönderim yapılmadı.
- Güvenli etkileşimli `madematik:create-super-user` komutu var; otomatik yönetici veya sabit parola oluşturulmadı.
- aWa yalnız ürün tanıtımı/bağlantıdır; ayrı hesap, uygulama ve DB olarak korunur. Bağımsız fiziksel uygulama dizinleri için mevcut `-d`/`-f` rewrite önceliği ve seçici yayın/geri dönüş planı belgelendi.

## Yapılan hafif kontroller

- Yeni izole MariaDB'de migration/ilk seed başarıyla çalıştı; başka DB'ye işlem uygulanmadı.
- PHP kaynak sözdizimi, route listesi ve route cache kontrolü yapıldı.
- Vite production build geçti; bütün derlenen Blade/PHP şablonları sözdizimi kontrolünden geçti.
- Ana sayfa ve giriş sayfası HTTP 200 döndürdü; giriş formunda CSRF/parola alanı bulundu.
- İlk ana sayfa ve admin ayarları şablonlarında bulunan Blade hataları geliştirmenin içinde giderildi.

## Aşama 4 — yerel doğrulama

- PHPUnit: **96 test, 926 assertion** başarılı. Her test yeni SQLite bellek veritabanında çalışır; mevcut geliştirme DB'si temizlenmez.
- Saat & Sayaç: **10/10** Node testi başarılı; kalıcılık, duraklama/devam, bitiş, bozuk depolama ve geri alınan sistem saati kontrol edildi.
- Chromium: **217/217** kontrol başarılı. Kamu sayfaları 320/390/768/1280/1920 px açık/koyu temalarda; üye ve yönetici ekranları, slider, mobil menü ve sayaç etkileşimleri doğrulandı. İncelenen ekranlarda axe WCAG 2 A/AA ve 2.1 AA ihlali veya JavaScript hatası bulunmadı.
- Özel QA MariaDB: **12/12** kontrol başarılı. Migration, zorunlu yorum onayı, görünürlük, bildirim, commit sonrası kuyruk ve gerçek yerel worker/log mailer akışı doğrulandı. 45 örnek projeli liste isteği 9 sorgu kullandı.
- Apache fixture: **17/17** kontrol başarılı. Yeni sahte fiziksel uygulama dizinleri `-d`/`-f` önceliğiyle Laravel fallback'ten korunuyor; kaynak uygulamalar kopyalanmadı.
- Kayıt hukukî bağlantısı, hatalı dizi girdileri, JSON Türkçe arama, medya hata alanı, Alpine erişilebilirlik nitelikleri, sayaç dayanıklılığı ve dar yönetim başlığı düzeltildi. Veritabanı komutlarında gerçek bağlantı/sahiplik kontrolü ve son etkin Super User koruması güçlendirildi.
- Ayrıntılı kapsam, ölçümler ve doğrulama sınırları [test sonuçlarında](test-results.md) kayıtlıdır.

## Açık kalanlar

- Kullanıcı güvenli Super User hesabını etkileşimli komutla oluşturabilir; gerçek iletişim adresini, biyografisini ve sosyal bilgilerini panelden tamamlayabilir.
- Hukukî taslaklar veri sorumlusu/saklama/sağlayıcı bilgileriyle tamamlanmalı ve nihai inceleme görmelidir.
- cPanel yolları, ayrı üretim DB'si, PHP/HTTPS, gerçek SMTP ve cron/worker ancak ayrıca onaylı canlı hazırlığında kesinleştirilebilir.
- Bağımsız uygulama adresleri kullanıcı talimatı ve yerel dosya adlarına dayanır; gerçek sunucu dizinleri ve URL harf düzeni canlı erişim onayından sonra doğrulanmalıdır.
- Kullanıcı hesabını kalıcı silmek kendi yorumlarını ve başka üyelerin bağlı yanıtlarını da cascade ile siler; kapsam yönetim arayüzünde açıkça belirtilir. Ayrı iletişim/audit kayıtları için saklama yükümlülükleri ayrıca değerlendirilir.

Sonraki sınır canlıya geçiştir. [Aşama 4 test planı](stage-4-test-plan.md) yerel doğrulamanın kapsamını, [operasyon belgesi](operations.md) ayrıca onay gerektiren yayın ve geri dönüş adımlarını içerir. Yerel testlerin geçmesi gerçek hosting, SMTP ve üretim dizinlerinin doğrulandığı anlamına gelmez.
