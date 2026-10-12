# Geliştirme durumu — 12 Ekim 2026

İlk üç geliştirme aşaması uygulandı. Aşama 4 kapsamlı testleri ve canlı yayın başlatılmadı. Bu rapor, uygulamanın kapsamlı testlerden geçtiği veya üretime dağıtıldığı anlamına gelmez.

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
- Kapsamlı PHPUnit/E2E, mobil tarayıcı, erişilebilirlik/performans/güvenlik taraması, SMTP veya canlı regresyon testi yapılmadı.

## Açık kalanlar

- Üyelik, moderasyon, bildirim/mail, admin CRUD ve sayaç davranışlarının uçtan uca doğrulanması Aşama 4 onayını bekliyor. Hafif kontroller bu akışların tamamının geçtiğini kanıtlamaz.
- Kullanıcı güvenli Super User hesabını etkileşimli komutla oluşturabilir; gerçek iletişim adresini, biyografisini ve sosyal bilgilerini panelden tamamlayabilir.
- Hukukî taslaklar veri sorumlusu/saklama/sağlayıcı bilgileriyle tamamlanmalı ve nihai inceleme görmelidir.
- cPanel yolları, ayrı üretim DB'si, PHP/HTTPS, gerçek SMTP ve cron/worker ancak ayrıca onaylı canlı hazırlığında kesinleştirilebilir.
- Bağımsız uygulama adresleri kullanıcı talimatı ve yerel dosya adlarına dayanır; gerçek sunucu dizinleri ve URL harf düzeni canlı erişim onayından sonra doğrulanmalıdır.
- Kullanıcı hesabını kalıcı silmek kendi yorumlarını ve başka üyelerin bağlı yanıtlarını da cascade ile siler; kapsam yönetim arayüzünde açıkça belirtilir. Ayrı iletişim/audit kayıtları için saklama yükümlülükleri ayrıca değerlendirilir.

Sonraki adım: [Aşama 4 test planı](stage-4-test-plan.md) için açık kullanıcı onayı. Kapsamlı test onayı canlı yayın onayı değildir.
