# Aşama 4 — yerel test sonuçları

**Tarih:** 12 Ekim 2026. Kullanıcının “commit+push dan sonra kapsamlı testlere başla” talimatı üzerine başlangıç uygulaması `bbaa679` commit'i ile `main` dalına push edildi; ardından aşağıdaki testler yürütüldü. Bulunan sorunlar yeni mADEMatik uygulamasında giderildi. Canlı sunucuda işlem veya dağıtım yapılmadı.

## Sonuçlar

| Kontrol | Ölçülen sonuç | Kapsam |
| --- | --- | --- |
| PHPUnit | **96 test / 926 assertion**, başarılı | Üyelik, yetki, profil, admin CRUD, medya, yorum, mail/bildirim, SEO ve güvenlik regresyonları |
| Node sayaç testleri | **10/10**, başarılı | Kalıcılık, zaman hesapları, duraklatma/devam, bozuk veya kapalı depolama, odak davranışı |
| Chromium ana tarayıcı kapsamı | **191/191**, başarılı | Kamu/üye/admin sayfaları, tema, mobil menü, slider, sayaç, klavye ve tek logo isteği |
| Chromium yönetim reflow koşusu | **26/26**, başarılı | 320/390/1280 px, açık/koyu tema, standart ve 100 karakter yönetici adı |
| Apache fiziksel dizin fixture | **17/17**, başarılı | Fiziksel dizin/dosya önceliği, harf düzeni, eğik çizgi ve Laravel fallback |
| Özel QA MariaDB | **12/12**, başarılı | Migration, yorum görünürlüğü, bildirim, kuyruk/worker ve TR/EN mail altyapısı |
| Sorgu ölçümü | **45 örnek projede 9 sorgu** | Sayfalı proje listesi; ilgili senaryoda N+1 artışı görülmedi |
| Derleme ve bağımlılıklar | Vite derlemesi başarılı; Composer/npm uygulama denetimlerinde bilinen güvenlik açığı bulunmadı | Kilitli uygulama bağımlılıkları; yayın ortamı denetimi değildir |

Son build üzerinde tek tam tarayıcı koşusu **217 başarılı kontrol, 0 başarısız kontrol** ile tamamlandı. Kamu sayfaları 320/390/768/1280/1920 px genişliklerde açık ve koyu temayla incelendi. Üye/admin ekranları ve dar yönetim başlığı ayrıca kontrol edildi. Kayıtlı koyu tema tercihinde yalnız doğru PNG'nin yüklenmesi, tema geçişi ve yeniden yüklemede tercihin korunması doğrulandı. İncelenen ekranlarda JavaScript hatası ve axe'ın WCAG 2 A/AA, WCAG 2.1 AA kurallarına göre ihlal bulunmadı. Otomatik axe sonucu tam erişilebilirlik sertifikası değildir; gerçek yardımcı teknoloji ve kullanıcı deneyimi ayrıca değerlendirilebilir.

## Ortam ve veri sınırları

Testler Laravel 13/PHP 8.4, Node 20, MariaDB ve yerel Chromium ile yalnız proje alanında çalıştı. PHPUnit her test için yeni SQLite `:memory:` veritabanı oluşturur; başlangıç migration'larını bu boş veritabanına uygular. `migrate:fresh`, `db:wipe` veya mevcut geliştirme verisini temizleyen bir işlem kullanılmadı.

MariaDB ve tarayıcı testleri için TCP erişimi kapalı, ayrı veri dizini ve soketi olan yeni QA örneği oluşturuldu. Sahte `.test` alıcılar ve yalnız test hesapları kullanıldı. PHPUnit mail fake/array, çalışma zamanı ise log mailer kullandı. SMTP gönderimi ve gerçek alıcılara e-posta gönderimi yapılmadı. QA kimlik bilgileri, `.env` ve anahtarlar Git'e veya bu rapora dahil edilmez.

Apache deneyi yeni sahte `drAW`, `GeoMatik2D`, `GeoMatik3D`, `avCELL` dizinlerinde çalıştı. Bağımsız projelerin dosyaları kopyalanmadı veya değiştirilmedi; aWa uygulamasına, hesabına ve veritabanına bağlantı kurulmadı. Tarayıcıda mevcut uygulama bağlantılarının hedefleri kontrol edildi, bu hedeflerde test çalıştırılmadı.

## Doğrulanan işlevler

- Kayıt/giriş/çıkış, e-posta doğrulama, parola reset/değişim, profil ve bildirim tercihleri; hatalı giriş ve doğrulama sınırları. Kayıtta ayrıcalıklı rol gönderimi yönetici oluşturmuyor.
- Üye, doğrulanmamış kullanıcı ve engelli hesap için sunucu tarafı erişim denetimi; admin parola onayı; güvenli Super User komutunun etkileşimli parola/teyit davranışı ve sabit hesap üretmemesi.
- Her yeni yorum ve yanıtın `pending` başlaması, düzenlenen yorumun tekrar onay beklemesi, halka yalnız onaylı ve uygun ebeveyn/proje ilişkili yorumların görünmesi. Moderasyon, şikâyet, sahiplik, rate limit ve XSS kaçışları.
- Bildirim sahipliği ve okunma durumu, yorum/iletişim bildirimleri, bağımsız site/mail tercihleri. MariaDB'de mail işi yalnız işlem commit edildikten sonra kuyruğa girdi ve gerçek yerel worker ile log mailer üzerinden işlendi. Sahte transport hatasında üç deneme ve başarısız iş kaydı ayrıca test edildi.
- Proje/kategori CRUD, Türkçe arama, slug ve dış URL denetimi, medya kapak/sıralama/silme, güvenli raster yükleme ve WebP üretimi; site/menü/slider/SEO ayarları ve üye/veri talebi akışları.
- Türkçe kamu sayfaları, hukukî taslak bağlantıları, sitemap/robots/metadata; yayınlanmamış proje görünürlüğü. Çevrilmemiş İngilizce sayfa ve dil seçici yayınlanmıyor; mail yerelleştirme altyapısı TR/EN için çalışıyor.
- Açık/koyu tema kalıcılığı, manuel slider, azaltılmış hareket, mobil menü, form etiketleri ve tablo klavye erişimi. Saat/Sayaç başlatma/duraklatma/devam/sıfırlama, yeniden açılınca doğru bitiş, tam ekran ve API yokken büyütme/odak davranışları.
- CSRF, XSS, yetkisiz nesne erişimi, zararlı URL ve dosya girdileri, throttling, sentetik HTTPS güvenlik başlıkları/cookie ayarları; veritabanı sahipliği ve yıkıcı komut engelleri.

## Giderilen bulgular

| Bulgu | Son davranış |
| --- | --- |
| Kayıt sayfasında yanlış hukukî route adları | Doğru hukukî sayfalar açılır; üyelik aydınlatma bağlantısı görünür. |
| E-posta/token/parola ve filtre alanlarında dizi girdisiyle 500 | Tip denetimi ve güvenli normalizasyon hatalı isteği doğrulama hatası veya güvenli varsayılan ile karşılar. |
| JSON proje başlığında Türkçe arama ve LIKE jokerleri | Türkçe JSON alanı aranır; jokerler escape edilir. |
| Kapak yüklemesinde hata yanlış form alanına bağlı | Hata kapak alanında gösterilir; raster piksel sınırı açıkça uygulanır. |
| Ana sayfa başlığında marka tekrarı | Admin SEO başlığı tutarlı şekilde kullanılır. |
| Blade/Alpine erişilebilirlik nitelikleri | İstemci bağları doğru escape edilir ve etkileşim sırasında güncellenir. |
| Sayaçta bozuk depolama, süre ve tam ekran odağı | Kayıtlar doğrulanır; süreler normalize edilir, depolama hatası bildirilir ve odak görünür etkin kontrollerde kalır. |
| Mobil hero ve uzun yönetici adında yatay taşma | Ortak tipografi/sarma ve daralan başlık düzeni 320 px kontrolünden geçer. |
| Footer/table/form bağlantılarında erişilebilirlik sorunları | Semantik navigasyon, etiketli klavye erişimli tablo bölgesi ve belirgin bağlantı stili kullanılır. |
| Veritabanı komutunda alternatif bağlantı ile sahiplik korumasını aşma riski | Gerçek `--database` hedefi doğrulanır; URL/read-write alternatifleri reddedilir, `migrate:install` de korunur. Test ortamında konsol güvenlik olayları etkin kalır. |
| Eşzamanlı Super User engellemesinde son etkin yönetici riski | Yönetici kümesi sabit sırayla işlem içinde kilitlenir; aktör yeniden doğrulanır ve son etkin hesap korunur. Regresyon testleri stale aktör ve son yönetici durumlarını kapsar. |
| Mobilde iki tema logosu ve ayrı font ağırlıkları birlikte yükleniyor | Merkezi logo bileşeni yalnız etkin temanın orijinal PNG'sini yükler; Inter Variable ağırlıkları iki yerel font kaynağından gelir. Kanonik marka dosyaları değiştirilmedi. |

## Performans ölçümü

Lighthouse 12.8.2 ile ana sayfada her profil için bir soğuk başlangıç ölçümü alındı; logo/font iyileştirmesinden sonra aynı profiller tekrar ölçüldü.

| Profil | Önce → sonra | FCP | LCP | TBT | CLS | Aktarım / istek |
| --- | --- | --- | --- | --- | --- | --- |
| Mobil | **72 → 90/100** | 2,256 sn | 3,231 sn | 106 ms | 0,00675 | 504.847 bayt / 11 |
| Masaüstü | **99 → 100/100** | 0,523 sn | 0,723 sn | 0 ms | 0,00048 | 554.153 bayt / 11 |

Mobil aktarım yaklaşık %25, masaüstü aktarım %29 azaldı. Son ölçümlerde yalnız seçilen tema logosu ve iki font kaynağı yüklendi; dış sayfa isteği, HTTP hatası veya Lighthouse çalışma uyarısı görülmedi. [Fontsource değişken font yaklaşımı](https://fontsource.org/docs/getting-started/variable) ile Inter ağırlıkları aynı dosyalardan sağlanır; marka paketindeki PNG'ler aynen kullanılır.

Ölçüm [resmî Lighthouse CLI seçenekleri](https://github.com/GoogleChrome/lighthouse#cli-options) üzerinden sistem Chromium ile yalnız loopback üzerinde yapıldı. Araç `.local/lighthouse` altında izole tutulur ve uygulamanın dağıtım bağımlılıklarına eklenmez. Yerel PHP geliştirme sunucusu, sıkıştırılmamış HTTP/1.1 ve simüle cihaz/ağ koşulları kullanıldı. Sonuç tek koşuluk yerel karşılaştırmadır; saha Core Web Vitals sonucu, yük testi veya canlı hosting garantisi değildir. Mobil LCP 3,231 sn olduğundan gerçek HTTPS/sıkıştırma/önbellek koşullarında yeniden ölçüm yayın hazırlığında kalır.

## Tekrar çalıştırma ve kanıtlar

Proje kökünde, bağımlılıklar kurulduktan sonra bağımsız işlev ve sayaç testleri:

```bash
vendor/bin/phpunit
node --test tests/Browser/clock.test.mjs
```

Tarayıcı ve MariaDB kontrolleri yalnız yeni ve sahipliği doğrulanmış özel QA ortamında çalıştırılır. `scripts/browser-qa.mjs`, `scripts/check-qa-runtime.php` ve `scripts/check-public-routing.py` bu senaryoları içerir. [QA kurulum/çalıştırma belgesi](qa-environment.md) mevcut veriyi silmeden başlatma/kapatma adımlarını açıklar. Yeni yardımcı betikte sözdizimi, mevcut QA context uyumu ve üzerine yazmayı reddetme kontrolü yapıldı; gerçek QA instance'ında başlatma/kapatma/durum kontrolleri geçti. Temiz klonda ilk kurulum senaryosu ayrıca yürütülmedi. Mevcut proje veya üretim veritabanı bir test hedefi olarak kullanılmaz. Test sonunda QA HTTP/Apache/MariaDB süreçleri kapatıldı; test verileri korundu.

Makineye özel rapor ve ekran görüntüleri Git dışında `storage/runtime/` altında saklanır:

- `qa/browser-report-final.json`: son build üzerinde 217 tarayıcı kontrolü; `qa/` altında 20 ekran görüntüsü. İlk 190 + 26 kontrollük raporlar da korunur.
- `routing-report.json`: 17 Apache fixture kontrolü.
- `qa-runtime-report.json`: 12 MariaDB kontrolü ve sorgu sayısı.
- `qa/lighthouse-home-{mobile,desktop}-optimized.report.{json,html}` ve `qa/lighthouse-optimized-summary.{json,md}`: son performans ölçümleri; ilk ölçümler de korunur.

## Yayından önce kalanlar

Yerel sonuçlar geçmiştir. Gerçek cPanel/PHP-FPM/document root, HTTPS/SSL, ayrı üretim DB sahipliği, dosya izinleri, gerçek SMTP ve cron/worker çalışma biçimi ayrıca onaylanmış canlı hazırlığında doğrulanmalıdır. Gerçek bağımsız uygulama dizinlerinin ve URL harf düzeninin korunması aynı hazırlığın parçasıdır; yerel fixture deneyi mevcut sunucuyu doğrulamaz.

Hukukî metinler nihai inceleme gerektiren taslaktır. Gerçek biyografi, iletişim ve sosyal bilgiler kullanıcı tarafından tamamlanmalıdır. Hesap silme, üyeye ait yorumları ve bağlı yanıtları cascade ile kalıcı siler; ayrı iletişim/audit verileri için saklama kararı ayrıca değerlendirilir.

Kapsamlı test onayı canlı yayın onayı değildir. Açık yayın onayı gelmeden üretimde inceleme, değişiklik veya dağıtım yapılmaz. Yayın/yedek/geri dönüş adımları [operasyon belgesindedir](operations.md).
