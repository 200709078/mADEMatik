# AGENTS.md — mADEMatik Solutions / madematik.com

> **Görev:** `~/Belgeler/mADEMatik` klasörünün **doğrudan kökünde**, mevcut madematik.com'un yerine geçecek yeni, üretime hazırlanabilir Laravel 13 web uygulamasını geliştir.
> **Dil:** Kod ve teknik isimlerde yerleşik İngilizce standartları kullan; ziyaretçiye görünen bütün ilk sürüm metinleri ve Codex ilerleme raporları Türkçe olsun.
> **Öncelik:** Verinin korunması, çalışan bağımsız uygulamalara dokunulmaması, tek bir tasarım sistemi, tamamlanmış işlevler, az sayıda büyük geliştirme aşaması.

## 0. Yetki sınırları ve kesin yasaklar

1. **Tek yazılabilir proje alanın:** `~/Belgeler/mADEMatik` ve bu projeye özel, açıkça oluşturduğun geçici derleme/kurulum alanlarıdır. Mevcut diğer projelere dokunma. Kullanıcının açık onayı olmadan canlı sunucuda işlem yapma.
2. `~/Belgeler` içerisindeki **Learn English with ALA, drAW, GeoMatik2D, GeoMatik3D, avCELL, aWa ve diğer projeler salt okunur referanstır.** Dosyalarını okuyabilir, mimarilerini inceleyebilirsin; hiçbirini **değiştirme, silme, taşıma, üzerine yazma, formatlama, bağımlılıklarını kurma/güncelleme, migration/seed/test komutlarını orada çalıştırma**. Gerekli işlevleri yeni mADEMatik projesinde yeniden oluştur veya uyumlu şekilde uygula. Orijinal projeleri ve `.env` bilgilerini raporlara kopyalama.
3. **aWa'nın üretim veritabanına ve çalışan uygulamasına kesinlikle müdahale etme.** `awa.madematik.com` bağımsız uygulama, bağımsız veritabanı ve bağımsız yönetim paneli olarak kalacak. `migrate:fresh`, `db:wipe`, `migrate`, `seed` vb. hiçbir veritabanı komutunu aWa bağlantısında çalıştırma; aWa kullanıcılarını değiştirme. aWa için ortak SSO veya doğrudan çapraz veritabanı bağlantısı kurma.
4. Eski madematik.com'dan **kod, makale, kullanıcı, tablo, ayar, eski logo veya demo içerik aktarma**. Ana siteyi sıfırdan geliştir. Eski madematik.com URL'leri için sırf geçmişi korumak amacıyla yönlendirme hazırlama.
5. `drAW`, `GeoMatik2D`, `GeoMatik3D`, `avCELL` gibi bağımsız web uygulamalarının **mevcut `madematik.com/<klasör>` adreslerinden yayınlanmasına devam edilecek**. Bunları şimdi subdomainlere taşıma; mevcut yayın klasörlerine ve içeriklerine zarar verme. Ana Laravel yönlendirmeleri/web sunucu kuralları mevcut fiziksel public dizinlerini ezmemeli. Yerel projede bu bağımsız uygulamaları yeniden üretmek veya kaynak klasörlerini izinsiz kopyalamak zorunda değilsin; **canlıya alma planında** mevcut yayın dizinlerini koru.
6. Yeni proje yalnızca **kendisine ait yeni, ayrı bir veritabanı** kullanacak. Belirsiz veya önceden var olan DB adına bağlanıp tablo silme/değiştirme. Güvenli isim (ör. yerelde `madematik_v2`) ve ayrı DB kullanıcısı seç; çakışma/erişim belirsizliğinde dur ve kullanıcıya sor. `.env` gizli dosyadır; parola veya anahtarları kodda/raporda tutma.
7. Kullanıcı onayı olmadan domain, cPanel, DNS, SSL, üretim dosyaları, gerçek e-posta alıcıları veya gerçek kullanıcı verileri üzerinde işlem yapma. Önceden tanımlı kritik güvenlik sınırlarını sessizce aşma.

## 1. Proje kökü, teknoloji ve çalışma ilkesi

- Hedef klasör: **`~/Belgeler/mADEMatik`**. Laravel uygulaması **doğrudan bu klasöre** kurulacak; içeride ikinci bir `mADEMatik/`, `backend/` veya `site/` adlı uygulama kökü oluşturulmayacak.
- `AGENTS.md` mevcutsa klasör boş olmayabilir. Laravel kurucusu boş dizin istiyorsa geçici dizine kurup Laravel dosyalarını (gizli dosyalar dahil) hedef klasörün köküne güvenli biçimde aktar; **AGENTS.md'yi koru**, ikinci bir proje klasörü bırakma.
- Geliştirme ortamı Pardus GNU/Linux (Debian tabanlı), VS Code, PHP 8.4, Composer, MariaDB, Node/npm. Önce sürümleri ve eksik PHP eklentilerini **yalnızca gözlemle**; gerekli kurulumlar için güvenli/az müdahaleli yaklaşımı seç.
- **Laravel 13**, **Blade**, **Tailwind CSS 4**, küçük istemci etkileşimlerinde **Alpine.js**; yalnızca gerçekten gerekirse Livewire. Vue/Inertia veya kapsamlı SPA altyapısı bu ana site için gerekli değil.
- MariaDB/MySQL uyumlu migration'lar, Vite, Laravel'in yerleşik kimlik doğrulama/doğrulama, authorization, mail, notification, queue, scheduler altyapıları tercih edilir.
- Güvenlik, sürdürülebilirlik, sade mimari, mobil uyumluluk, performans ve erişilebilirlik öncelikli. Geliştirme ortamında üçüncü taraf proje ve veritabanlarından bağımsız çalışmalı.
- Karar verilmiş konuları tekrar kullanıcıya sorma. Eksik küçük ayrıntılarda makul ve tutarlı bir varsayım yapıp belgeleyebilirsin. **Yıkıcı işlem, üretim erişimi, gizli bilgi, mevcut kaynak projeye değişiklik veya önemli kapsam değişikliği** gerekiyorsa önce durup sor.

## 2. Marka ve görsel paket

**Ana marka:** `mADEMatik Solutions`. Kısa kullanım: `mADEMatik`. Marka, yazılım ve teknoloji çözümleri üreten; eğitim alanında da çalışan bir stüdyoyu temsil eder. Alt ürünler: `aWa`, `drAW`, `GeoMatik`, `avCELL` ve yenileri. `aWa` yazımı kesindir; `AWay` yazma.

- Hazırlanmış kaynak paket: **`mADEMatik_Solutions_Gorsel_Paketi.zip`**. Önce `~/Belgeler/mADEMatik-Gorseller/` altında ara. Kullanıcı başka yerde tutmuşsa yalnızca okuma için `~/Belgeler`, `~/Masaüstü` veya `~/Desktop` altında dosya adına göre bak. **Bulamazsan marka görsellerini yeniden icat etme; kullanıcıdan paketin yerini belirtmesini iste.** Diğer bağımsız geliştirmeleri sürdürebilirsin.
- Paketi oku: `KULLANIM.md`, `manifest.json`, `sources/`, `logos/`, `icons/`, `banners/`, `social/`, `placeholders/`. Hazır görsellerin **yalnızca gerekli kopyalarını** yeni projenin örneğin `public/assets/brand/` altına al; asıl arşivi ve kaynaklarını değiştirme. `manifest.json` içerik doğrulamasını mümkünse denetle.
- **Kanonik MS sembolü**, paketteki `sources/kabul-edilen-orijinal-ms.png` referansıdır. Rengini, harf geometrisini, M/S ilişkisini, degrade yönünü veya rozetini farklı sayfalarda değiştirme. Açık/koyu tema için gerektiğinde logonun yazı kısmı uyarlanabilir; **MS simgesinin kendisi aynı kalır**. Tercih edilen kaynaklara `logos/logo-horizontal-light.png`, `logos/logo-horizontal-dark.png`, `logos/logo-header-light.png`, `logos/logo-header-dark.png`, `icons/ms-brand-icon-1024.png` dahildir. Logoları yeniden üretmeye çalışma.
- `icons/favicon.ico`, farklı boyut PNG ikonlar, Apple Touch/PWA ikonları; `social/og-madematik-1200x630.png` sosyal önizlemede; `placeholders/proje-varsayilan-1200x675.webp` varsayılan proje kapağında kullanılsın.
- **Ana sayfada üç slaytlı sade slider:** `banners/slider-01-madematik.webp`, `slider-02-projelerim.webp`, `slider-03-egitim-teknoloji.webp` ve mobil eşleri. Slayt konuları sırasıyla mADEMatik Solutions, Projelerim, Eğitim & Teknoloji. Başlık/açıklama/CTA metinlerini **resmin içine gömme; erişilebilir HTML olarak ayrı tut**, yönetim panelinden düzenlenebilir yap. Kullanıcı kontrollü ileri/geri/nokta düğmeleri ve makul geçişler olsun; otomatik oynatma kullanılacaksa durdurulabilir olsun, azaltılmış hareket tercihine uyulsun. İlk slaytı gereksiz lazy-load etme.
- Mevcut paket raster (PNG/WebP/ICO) dosyaları içerir; **SVG bulunduğunu varsayma veya PNG'yi sahte SVG olarak sunma**. Diğer ürün logolarını paket yoksa üretmek yerine ürün adıyla metinsel sun.

## 3. Merkezi tasarım sistemi — zorunlu

**Sayfaları geliştirmeden önce** ortak token ve bileşen altyapısını kur. Tasarıma yönelik ayrı önizleme/karar-onay aşaması oluşturma; kullanıcı görsel kararları Codex'e bırakmıştır.

- Tutarlı `Inter` sans-serif tipografi, gerektiği yerde monospace; marka paketindeki mavi tonlarına uyumlu açık/koyu tema, kontrastlı yüzeyler, 4px tabanlı spacing, tutarlı radius, border ve gölgeler.
- Tekil ve yeniden kullanılabilir Blade UI bileşenleri: `Button` (primary/secondary/outline/ghost/danger, boyutlar, loading/disabled), `Input`, `Textarea`, `Select/Combobox` (arama gerektiğinde), `Checkbox`, `Radio`, `Label`, `Modal/Confirm`, `Alert/Toast`, `Badge`, `Card`, `Table`, `Pagination`, `Tabs`, `Dropdown`, `Avatar`, `Skeleton`, `EmptyState`, `NotificationBell`, `ThemeToggle` vb. Gereksiz soyutlama yapma; gerekli bileşenleri merkezi geliştir.
- CSS tasarım değişkenleri/tema tokenları merkezi olsun. Sayfa ve modüllerde rastgele renk/font/köşe yarıçapı veya yinelenmiş buton CSS'i yazma. Yeni modül mevcut bileşenleri kullansın.
- **Frontend:** Modern, sade, özenli portfolyo. **Admin:** Daha kompakt, standart, verimlilik odaklı; kendi içinde aynı ölçüde tutarlı. İkisinin görünümü tamamen aynı olmak zorunda değil, fakat ortak marka/tipografi/token ilkeleri korunmalı.
- Her iki tarafta da **açık/koyu tema**: ilk ziyarette işletim sistemi tercihine uy, kullanıcı seçimini kalıcı olarak tarayıcıda sakla, tema geçişinde içerik okunaklı kalsın ve ilk yüklemede görünür tema parlamasını önlemeye çalış.
- Mobil/tablet/masaüstü uyum; klavye odağı, form etiketleri, doğrulama hataları, kontrast, semantik HTML, ekran okuyucu desteği ve `prefers-reduced-motion` gözet.
- İkonlar için tek kütüphane (örn. Lucide) seç; farklı yerlerde rastgele ikon setleri karıştırma.

## 4. Ziyaretçi sayfaları ve içerik modeli

**Ana menü:** `Ana Sayfa`, `Hakkımda`, `Projelerim`, `Saat & Sayaç`, `İletişim`. **Özgeçmiş** ve **Yazılarım / Blog** sayfaları **yok**. Kullanıcı giriş yaptığında profil ve bildirim bağlantıları; Super User için yönetim paneli bağlantısı gösterilebilir.

| Sayfa | İstenen adres / işlev |
| --- | --- |
| Ana Sayfa | `/` — üç slaytlı hero, dengeli eğitimci+yazılımcı tanıtımı, uzmanlık alanları, öne çıkan projeler, ziyaretçi görüşleri, iletişim çağrısı |
| Hakkımda | `/hakkimda` — kişisel ve mesleki tanıtım, eğitim ve teknoloji yaklaşımı, kullanılan araç/teknolojiler; bağımsız CV sayfası yok |
| Projelerim | `/projelerim` — filtrelenebilir proje kartları, kategori/durum, kapak görseli, arama/gezinti |
| Proje detayı | `/projelerim/{slug}` — tanıtım, özellikler, ekran görüntüleri, teknolojiler, durum, uygulamaya erişim, projeye özel yorumlar |
| Yorumlar | `/yorumlar` — onaylı genel ziyaretçi yorumları, ilgili sayfalardan erişim; ana menüye eklenmesi zorunlu değil |
| Saat & Sayaç | `/saat-sayac` — aynı ana site içindeki araç |
| İletişim | `/iletisim` — iletişim formu, e-posta ve gerekli bağlantılar |
| Üyelik ve profil | Kayıt, giriş, e-posta doğrulama, şifre sıfırlama, `/profilim`, hesap/bildirim tercihleri |
| Yönetim paneli | `/admin` — yalnızca ana sitenin Super User hesabı |

**Saat & Sayaç:** Gerçek yerel saati, kalıcı geri sayım sayacı (başlangıç/bitiş zamanını tarayıcı depolamasında sakla, sayfa tekrar açılınca doğru hesapla), başlat/duraklat/sıfırla, büyüt/küçült, `SAAT`, `SAYAÇ`, `ANA SAYFA` kontrolleri içerir. Türkçe ilk sürüm; İngilizceye hazır. Varsa `~/Belgeler` içindeki eski saat/sayaç tasarım notlarını **salt okunur** referans alabilirsin.

**Projelerim:** Eski "Makaleler" tablosunu taşıma. Yeni `projects`, `project_categories`, `project_media` vb. modeli tasarla. Projelerde başlık, slug, kısa/uzun açıklama, kategori, durum (`geliştiriliyor`, `yayında`, `tamamlandı` gibi), sıralama/öne çıkarma, teknolojiler, görseller ve isteğe bağlı dış bağlantı alanları olsun. drAW, GeoMatik, avCELL ve aWa örnekleri **gerçek bilgilerle** kullanıcı girdisine veya erişilebilir salt okunur referanslara dayanarak oluşturulabilir; bilinmeyen özellikleri uydurma. Yönetim paneli projeleri CRUD olarak yönetsin. Proje kartındaki `Uygulamayı Aç` bağlantıları gerçek mevcut adreslere gidebilsin; subdomain değişikliği yapma.

**Bağımsız uygulamalar:** `/drAW`, `/GeoMatik2D`, `/GeoMatik3D`, `/avCELL` gibi adresler Laravel'in fiziksel public klasörlerle çatışmayan web-server davranışı sayesinde korunmalı. Büyük/küçük harf farklarını mevcut adreslerden doğrula. `aWa` ayrı olarak `https://awa.madematik.com` adresinden çalışmaya devam etsin. Yeni mADEMatik sitesinde aWa'yı ürün olarak tanıt ve bağlantı ver; aWa kodunu yeniden yazma veya onun görünümünü değiştirmek için kaynağa müdahale etme.

## 5. Üyelik, yetkilendirme, yorumlar, e-posta ve bildirimler

### Roller / kullanıcı kapsamı

- **Ziyaretçi:** Giriş yapmamış kişidir; veritabanında ayrıca rol olması gerekmez. Herkese açık sayfaları ve **onaylı** yorumları görür, iletişim formunu kullanabilir.
- **Üye:** Herkese açık ve ücretsiz kayıt; e-posta doğrulamasından sonra yorum/yanıt yazabilir, profilini ve bildirim tercihlerini yönetebilir.
- **Super User:** madematik.com'da tam site yönetim yetkilisi, admin panelinin tek yönetici rolüdür. **Moderatör veya Editör rolü oluşturma.** Güvenli biçimde ayrı bir Super User hesabı oluşturulabilsin; sabit parola veya otomatik açık admin hesabı üretme.
- **Okul Yöneticisi:** **Yalnızca aWa içinde** bulunan rol/yetki kapsamıdır; bu rol için madematik.com'da okul yönetim modülü veya rol tablosu geliştirme. Super User, aWa'ya **ayrı aWa hesabıyla** giriş yapabilir; SSO zorunlu değil. aWa hesabı oluşturma/değiştirme bu projenin otomatik işlemi değildir.
- Her erişim **sunucu tarafında** middleware/Policy/Gate ile doğrulansın. Yetki denetimini yalnızca menü gizlemeye bırakma.

### Yorumlar — zorunlu onay

- Learn English with ALA projesinin mevcut yorum yaklaşımını **salt okunur** incele; yeni uygulamada Laravel 13 ve yeni veri modeline uygun olarak uygula.
- Genel ziyaretçi yorumları ve **Projelerim detay yorumları**, gerektiğinde bunlara yanıtlar desteklensin.
- **Her yeni yorum ve her yanıt `pending` başlar. Bütün yorumlar zorunlu olarak Super User onayından geçer; asla otomatik yayınlama yok.** Düzenlenmiş yorum da yeniden `pending` durumuna döner. Reddedilen ve bekleyen yorumlar halka açık görünmez; yazan üye kendi yorum durumunu görebilir.
- Admin'de onayla/reddet/gizle/sil ve filtreleme, makul anti-spam, rate limit, XSS koruması, gerektiğinde kullanıcı engelleme/yorum şikâyeti için yalın mekanizmalar olsun.
- Onaylama/reddetme işlemleri ve durumlar kayıt altına alınsın. Kullanıcıya uygun site içi bildirim üretilebilsin.

### E-posta ve bildirimler

- Learn English with ALA'nın mail kurulumunu ve e-posta şablonlarını **sadece referans olarak** incele; gizli ayarları veya SMTP parolalarını kopyalama. Laravel Mail/Notification altyapısını yeni sitede yeniden yapılandır. SMTP değerleri `.env`'den gelsin. Üretim dışı ortamda gerçek kullanıcılara e-posta gönderme.
- E-postalar: hesap doğrulama, parola sıfırlama, iletişim mesajı yönetici bilgilendirmesi, gerektiğinde yorum yanıtı/onay durumları; tercihe bağlı yeni proje duyurusu.
- Site içi bildirim: yeni yorum onayı bekliyor (Super User), yorum onaylandı/reddedildi (üye), yoruma yanıt, iletişim mesajı (Super User). Okunmuş/okunmamış, bildirim zili ve sayfası; bildirim tercihleri olanak dahilinde.
- Kritik hesap/güvenlik e-postaları isteğe bağlı duyurulardan ayrı yönetilsin. E-posta kuyruk işlemlerinin cPanel gibi paylaşımlı hostingde nasıl çalışacağı belgelenip güvenli şekilde planlansın; çalışmayan/kurulmamış kuyruk işleyicisine sessizce güvenme.

### Yönetim paneli

- `/admin`: dashboard, **Projelerim**, proje kategorileri/medya, yorum onayları, üyeler, iletişim mesajları, bildirimler, site ayarları (menüler, slider içerikleri, sosyal bağlantılar, SEO ve kurumsal bilgiler), temel işlem kayıtları.
- Tek ve tutarlı sade admin tasarımı. Admin'den içerik eklendiğinde kaynak kod düzenlemeye gerek kalmasın. Sadece gerçek ihtiyaca hizmet eden bölümler ekle; blog, karmaşık CMS ve okul yönetimini admin'e taşıma.

## 6. Veritabanı ve çok dillilik

- Mevcut madematik.com şemasına bağlı kalma. Yeni migration'lar **hedef şemayı doğrudan** oluştursun; eski tablolardan dönüşüm migration'ı yazma.
- Gerekli tabloları sade biçimde kur: `users`, proje/kategori/medya tabloları, genel/proje yorumları ve yanıtları, `settings`, `contact_messages`, `notifications`, kullanıcı bildirim tercihleri, gerekirse işlem kayıtları; Laravel auth/session/cache/queue tabloları. Rol sistemi yalnızca Üye/Super User ihtiyacına göre basit tutulabilir, gereksiz büyük RBAC paketi kurma.
- **Önce tam Türkçe sürümü geliştir.** Ancak yazılım altyapısı ilk günden **TR/EN** destekli olsun: Laravel çeviri dosyaları, çevirilebilir içerik modeli (tutarlı JSON veya ayrı çeviri tablosu stratejisinden birini seç), bildirim/mail yerelleştirme imkânı, dil bazlı SEO/URL tasarımı.
- Türkçe varsayılan ve URL'ler aynen `/`, `/hakkimda`, `/projelerim`, `/iletisim` vb. olsun. İleride `/{locale}` altında örneğin `/en/about`, `/en/projects` yapılabilir; İngilizce sayfalar çevrilmeden **yayınlama** ve dil seçiciyi gösterme. İlk sürümde TR dışı içerikler zorunlu değil. Yorumları kullanıcının yazdığı dilde sakla; otomatik çevirmeye çalışma.
- Yeni proje yalnızca kendi DB'sine migration/seed uygulasın; **yalnızca güvenli ve açıkça proje için oluşturulmuş yerel DB'de** ilk kurulum migration'ı çalıştır. Var olan herhangi bir DB'de `migrate:fresh`/`db:wipe` kullanma.

## 7. Güvenlik, SEO, performans ve operasyon

- CSRF, XSS, SQL injection önlemleri, doğrulama/escape, authentication ve authorization, throttling, güvenli dosya yükleme, görüntü optimizasyonu, güvenli oturum/cookie ayarları; Super User hesabına yönelik ek korumalar düşün.
- KVKK açısından gizlilik politikası, üyelik/aydınlatma metinleri, gerektiğinde çerez bilgilendirmesi ve kullanıcının hesap/veri silme talebini ele alma yolu. Hukuki metinler nihai hukukî onay gerektiren taslaklar olarak işaretlenebilir; gerçek onay olmadan kesin hukuk metni iddiası oluşturma.
- Proje ve sayfa bazlı başlık/meta açıklama/OG görseli, canonical, sitemap, robots, anlamlı slug, semantik HTML. Artık ihtiyaç duyulmayan eski URL'lere toplu yönlendirme hazırlama.
- 403/404/419/500/bakım ekranları ortak tasarım dilinde. Görsellerde responsive `srcset`/`picture` uygun olduğunda, lazy loading, boyutlar ve açıklayıcı `alt`; WebP öncelikli.
- Önerilen deploy: cPanel/PHP-Laravel uyumlu, derlenmiş Vite varlıkları, `.env` ve APP_KEY gizliliği, storage link, cache, gerekli worker/cron planı, yeni mADEMatik DB yedeği ve geri dönüş adımları. **Canlıya geçişte mevcut bağımsız public uygulama klasörleri ve aWa kesinlikle korunmalı.**
- Üretim veritabanı, barındırma yapılandırması ve mevcut public uygulamalar yalnızca açık kullanıcı onayı sonrasında incelenip değiştirilebilir.

## 8. Yalnızca DÖRT büyük geliştirme aşaması

**Kullanıcı projeyi 20–30 küçük parçaya bölmek istemiyor.** Aşağıdaki dört büyük aşama üzerinden ilerle; alt işlerini kendi içinde organize et. İlk üç aşamada mümkün olduğunca otonom çalış. Her aşama sonunda kısa Türkçe özet ver; her küçük bileşen için onay isteme.

### Aşama 1 — Çekirdek altyapı + tasarım sistemi

Laravel 13'ü **proje köküne** kur. Yalnızca bu yeni uygulama için ayrı yerel DB ve nihai migration yapısı, temel auth/role iskeleti, ortak frontend/admin layout, merkezi token ve UI bileşenleri, tema değiştirme, TR/EN'ye hazır localization altyapısını kur. Görsel paketini uygun klasörlere yerleştir; logo/favikon kaynaklarını sabit tut. Temel derlemenin gerçekleştiğini ve uygulamanın açıldığını **hafif kontrollerle** doğrula.

### Aşama 2 — Kullanıcıya görünen bütün sayfalar

Ana Sayfa + 3 banner slider, Hakkımda, Projelerim + detaylar + kategoriler, genel Yorumlar arayüzü, Saat & Sayaç, İletişim, hesap/profil görünümleri, responsive navigasyon/footer, SEO ve destekleyici sayfaları geliştir. Bütün içerik alanları gelecekte admin'den yönetilebilir olsun. Gerçek kaynaklarda olmayan proje bilgilerini uydurma; açıklama eksikse uygun boş durum kullan. Hafif doğrulamalarla ilerle.

### Aşama 3 — İşlevler + admin + entegrasyonlar

Herkese açık kayıt ve doğrulama, Üye/Super User yetkileri, **tüm yorumlarda zorunlu onay**, yorum yanıtları, bildirimler, mail gönderimi, iletişim formu, medya yönetimi, yönetim paneli CRUD ve site ayarlarını tamamla. Learn English with ALA'yı salt okunur referans olarak incele. aWa yalnızca ürün/bağlantı ve ayrı erişim bağlamında entegre edilsin; **aWa uygulamasını veya DB'sini değiştirme**. Bağımsız public dizinler için güvenli yayınlama yaklaşımını dokümante et. Geliştirmeyi mümkün olduğunca fonksiyonel ve tamamlanmış bir duruma getir.

### Aşama 4 — KAPSAMLI TESTLER + canlıya geçiş (ONAY GEREKİR)

**Bu aşamaya gelir gelmez DUR.** Kullanıcıya ilk üç aşamada tamamlananlar, kalan riskler ve uygulanacak test planını özetle. **Açıkça onay almadan kapsamlı testlere başlama.** Kullanıcı onay verince: işlevsel testler, kayıt/doğrulama, erişim kontrolü, yorum moderasyonu, bildirim/e-posta akışları (sahte veya test SMTP), admin CRUD, TR içerikleri, gelecek EN altyapısı, mobil görünüm, açık/koyu tema, erişilebilirlik, güvenlik, SEO, görüntü boyutları, performans, bağımsız `public` uygulama adreslerinin korunması ve regresyon kontrollerini yap; bulguları gider, sonuçları raporla. Kapsamlı test onayı **canlı yayın onayı değildir**. **Canlıya geçiş için ayrıca açık kullanıcı onayı al**; sonra yedek/geri dönüş planı ile dikkatli yayınla. aWa'nın çalışan veritabanına dokunma.

**Erken aşamalarda izin verilen hafif kontroller:** Dosya/sözdizimi/route kontrolü, lokal Vite derlemesi, izole yeni yerel DB'de migration doğrulaması, bir veya iki basit smoke kontrolü. Uzun kapsamlı test koşuları, yoğun tarama, büyük E2E setleri, canlı SMTP gönderimi ve üretime dağıtımı sona bırak. Bu yaklaşım bariz hataları görmezden gelmek anlamına gelmez; geliştirmeyi durduran hataları anında düzelt.

## 9. Raporlama ve tamamlanma ölçütü

- Gerekirse `README.md` veya kısa `docs/` dosyalarında yerel çalıştırma, ortam değişkenleri, migration/seed, görsel yolları, mail/kuyruk, deployment ve riskleri açıkla. `AGENTS.md` talimatlarını değiştirme; tutarsızlık fark edersen kullanıcıya bildir.
- Her aşama sonunda Türkçe olarak: **tamamlananlar, yapılan temel kontroller, kalan işler/riskler, sonraki aşama** bilgilerini kısa ver. Sürekli kullanıcıdan mikro-onay isteme.
- İlk üç aşama sonunda çalışan yerel Laravel sitesi, merkezi tasarım sistemi, kullanılabilir TR sayfalar, admin modülleri, üyelik/yorum/bildirim/mail altyapısı ve entegrasyon sınırları hazır olmalı. Yalnızca kapsamlı test, düzeltme ve onaylı canlı yayın işi kalmalı.
- Kullanıcı kapsamlı testlere veya canlıya geçişe onay vermemişse **kendiliğinden devam etme**.

**Kısa hatırlatma:** Ana markayı ve görselleri yeniden tasarlama; yalnızca `~/Belgeler/mADEMatik` içinde geliştir; diğer Belgelerim projelerini salt okunur incele; aWa'yı ve bağımsız uygulamaların mevcut yayınlarını koru; 4 büyük aşamada tamamla; kapsamlı testlerde ve canlıya çıkışta ayrı ayrı onay bekle.
