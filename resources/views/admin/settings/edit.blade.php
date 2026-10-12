@extends('layouts.admin')
@section('title','Site İçerikleri ve Ayarları')
@section('content')
<h1 class="heading-page mb-3">Site içerikleri ve ayarları</h1>
<p class="text-muted mb-6">Türkçe sayfa metinlerini, slider içeriklerini, sosyal bağlantıları ve arama motoru görünümünü yönetin.</p>
<form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">@csrf @method('PUT')
<div class="grid gap-6 lg:grid-cols-2">

<x-ui.card class="space-y-5">
<h2 class="text-xl font-semibold">Marka ve iletişim</h2>

@foreach(['name'=>'Marka adı','tagline'=>'Kısa slogan','description'=>'Marka açıklaması','email'=>'Kurumsal e-posta','location'=>'Konum'] as $field=>$label)

<x-ui.input :name="'brand['.$field.']'" :label="$label" :type="$field==='email' ? 'email' : 'text'" :value="old('brand.'.$field,$settings['brand'][$field] ?? '')" :error="$errors->first('brand.'.$field)"  />


@endforeach

</x-ui.card>

<x-ui.card class="space-y-5">
<h2 class="text-xl font-semibold">Ana menü başlıkları</h2>

@foreach(['home'=>'Ana Sayfa','about'=>'Hakkımda','projects'=>'Projelerim','clock'=>'Saat & Sayaç','contact'=>'İletişim'] as $field=>$label)

<x-ui.input :name="'navigation['.$field.']'" :label="$label" :value="old('navigation.'.$field,$settings['navigation'][$field] ?? $label)" :error="$errors->first('navigation.'.$field)" required  />


@endforeach

</x-ui.card>
</div>

<x-ui.card>
<h2 class="text-xl font-semibold mb-5">Ana sayfa: üç slayt</h2>
<div class="grid gap-6 lg:grid-cols-3">

@foreach($settings['sliders'] as $index=>$slide)<div class="space-y-4">
<h3 class="font-semibold">Slayt {{ $index+1 }}</h3>
<img src="{{ asset('assets/brand/'.$slide['image']) }}" alt="Slayt {{ $index+1 }} marka görseli" class="w-full aspect-video object-cover rounded-lg" loading="lazy">

@foreach(['title'=>'Başlık','description'=>'Açıklama','cta_label'=>'Düğme metni','cta_url'=>'Düğme bağlantısı'] as $field=>$label)

@if($field==='description')
<x-ui.textarea :name="'sliders['.$index.']['.$field.']'" :label="$label" :value="old('sliders.'.$index.'.'.$field,$slide[$field] ?? '')" :error="$errors->first('sliders.'.$index.'.'.$field)" rows="3" />


@else
<x-ui.input :name="'sliders['.$index.']['.$field.']'" :label="$label" :value="old('sliders.'.$index.'.'.$field,$slide[$field] ?? '')" :error="$errors->first('sliders.'.$index.'.'.$field)" required />


@endif

@endforeach</div>
@endforeach
</div>

</x-ui.card>

<x-ui.card>
<h2 class="text-xl font-semibold mb-5">Ana sayfa metinleri</h2>
<div class="grid gap-5 lg:grid-cols-2">

@foreach(['intro_title'=>'Tanıtım başlığı','intro_body'=>'Tanıtım metni','expertise_title'=>'Uzmanlık başlığı','expertise_body'=>'Uzmanlık açıklaması','projects_title'=>'Öne çıkan projeler başlığı','testimonials_title'=>'Ziyaretçi görüşleri başlığı','cta_title'=>'İletişim çağrısı başlığı','cta_body'=>'İletişim çağrısı metni','cta_label'=>'İletişim düğmesi'] as $field=>$label)

@if(str_ends_with($field,'body'))
<x-ui.textarea :name="'home['.$field.']'" :label="$label" :value="old('home.'.$field,$settings['home'][$field] ?? '')" :error="$errors->first('home.'.$field)" rows="3" />


@else
<x-ui.input :name="'home['.$field.']'" :label="$label" :value="old('home.'.$field,$settings['home'][$field] ?? '')" :error="$errors->first('home.'.$field)" />


@endif

@endforeach</div>
<h3 class="font-semibold mt-8 mb-5">Uzmanlık alanları</h3>
<div class="grid gap-5 lg:grid-cols-3">

@foreach(range(0,5) as $index)<div class="space-y-4">

<x-ui.input :name="'home[expertise_items]['.$index.'][title]'" :label="'Alan '.($index+1).' başlığı'" :value="old('home.expertise_items.'.$index.'.title',$settings['home']['expertise_items'][$index]['title'] ?? '')" :error="$errors->first('home.expertise_items.'.$index.'.title')" />

<x-ui.textarea :name="'home[expertise_items]['.$index.'][body]'" label="Açıklama" :value="old('home.expertise_items.'.$index.'.body',$settings['home']['expertise_items'][$index]['body'] ?? '')" :error="$errors->first('home.expertise_items.'.$index.'.body')" rows="3" />
</div>
@endforeach
</div>
<p class="field-help mt-3">Başlığı boş olan alanlar gösterilmez.</p>

</x-ui.card>

<x-ui.card>
<h2 class="text-xl font-semibold mb-5">Hakkımda</h2>
<div class="grid gap-5 lg:grid-cols-2">

@foreach(['title'=>'Sayfa başlığı','lead'=>'Giriş metni','body'=>'Kişisel ve mesleki tanıtım','approach_title'=>'Yaklaşım başlığı','approach_body'=>'Yaklaşım metni'] as $field=>$label)

@if(in_array($field,['lead','body','approach_body']))
<x-ui.textarea :name="'about['.$field.']'" :label="$label" :value="old('about.'.$field,$settings['about'][$field] ?? '')" :error="$errors->first('about.'.$field)" rows="5" />


@else
<x-ui.input :name="'about['.$field.']'" :label="$label" :value="old('about.'.$field,$settings['about'][$field] ?? '')" :error="$errors->first('about.'.$field)" />


@endif

@endforeach
<x-ui.input name="about[tools]" label="Araçlar ve teknolojiler" :value="old('about.tools',implode(', ',$settings['about']['tools'] ?? []))" :error="$errors->first('about.tools')" help="Virgülle ayırın." />
</div>

</x-ui.card>
<div class="grid gap-6 lg:grid-cols-2">

<x-ui.card class="space-y-5">
<h2 class="text-xl font-semibold">İletişim sayfası</h2>

@foreach(['title'=>'Başlık','lead'=>'Açıklama','email'=>'Görünür e-posta adresi'] as $field=>$label)
<x-ui.input :name="'contact['.$field.']'" :label="$label" :type="$field==='email' ? 'email' : 'text'" :value="old('contact.'.$field,$settings['contact'][$field] ?? '')" :error="$errors->first('contact.'.$field)" />

@endforeach

</x-ui.card>

<x-ui.card class="space-y-5">
<h2 class="text-xl font-semibold">SEO ve alt bilgi</h2>

<x-ui.input name="seo[title]" label="Varsayılan SEO başlığı" :value="old('seo.title',$settings['seo']['title'] ?? '')" :error="$errors->first('seo.title')" maxlength="160" required />


<x-ui.textarea name="seo[description]" label="Varsayılan meta açıklama" :value="old('seo.description',$settings['seo']['description'] ?? '')" :error="$errors->first('seo.description')" rows="3" maxlength="320" />


<x-ui.input name="footer[description]" label="Alt bilgi açıklaması" :value="old('footer.description',$settings['footer']['description'] ?? '')" :error="$errors->first('footer.description')" />


<x-ui.input name="footer[copyright]" label="Telif bilgisi" :value="old('footer.copyright',$settings['footer']['copyright'] ?? '')" :error="$errors->first('footer.copyright')" />


</x-ui.card>
</div>

<x-ui.card>
<h2 class="text-xl font-semibold mb-5">Sosyal bağlantılar</h2>
<div class="grid gap-5 lg:grid-cols-2">
@foreach(range(0,5) as $index)<div class="grid grid-cols-2 gap-3">

<x-ui.input :name="'social['.$index.'][label]'" :label="'Bağlantı '.($index+1).' adı'" :value="old('social.'.$index.'.label',$settings['social'][$index]['label'] ?? '')" :error="$errors->first('social.'.$index.'.label')" />

<x-ui.input :name="'social['.$index.'][url]'" label="URL" :value="old('social.'.$index.'.url',$settings['social'][$index]['url'] ?? '')" :error="$errors->first('social.'.$index.'.url')" />
</div>
@endforeach</div>
<p class="field-help mt-3">Adı ve URL alanları dolu bağlantılar gösterilir. http veya https kullanın.</p>

</x-ui.card>

<x-ui.card class="space-y-5">
<h2 class="text-xl font-semibold">Gizlilik ve üyelik metinleri</h2>

<x-ui.alert type="warning">Bu metinler hukuki inceleme için taslaktır. Yayın öncesi veri sorumlusu bilgileri, saklama koşulları ve başvuru kanalları tamamlanmalıdır.
</x-ui.alert>

@foreach(['privacy'=>'Gizlilik ve aydınlatma','terms'=>'Üyelik ve kullanım koşulları','membership'=>'Üyelik aydınlatma metni'] as $field=>$label)
<x-ui.textarea :name="'legal['.$field.']'" :label="$label" :value="old('legal.'.$field,$settings['legal'][$field] ?? '')" :error="$errors->first('legal.'.$field)" rows="10" />

@endforeach

</x-ui.card>

<x-ui.button type="submit" size="lg">Tüm Türkçe İçerikleri Kaydet
</x-ui.button>
</form>
@endsection
