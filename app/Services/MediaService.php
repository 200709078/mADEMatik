<?php
namespace App\Services;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class MediaService
{
    public function store(UploadedFile $file, string $field = 'image'): string
    {
        $info=@getimagesize($file->getRealPath());
        if (!$info || !in_array($info['mime'],['image/jpeg','image/png','image/webp'],true) || $file->getSize()>8*1024*1024 || $info[0]>6000 || $info[1]>6000 || $info[0]*$info[1]>24000000) {
            throw ValidationException::withMessages([$field=>'En fazla 8 MB, kenarı 6000 piksel ve toplamı 24 megapiksel olan JPG, PNG veya WebP yükleyin.']);
        }
        $image=@imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (!$image) throw ValidationException::withMessages([$field=>'Görsel okunamadı.']);
        $width=min(1920,$info[0]); $height=max(1,(int)round($info[1]*$width/$info[0]));
        $target=imagecreatetruecolor($width,$height); imagealphablending($target,false); imagesavealpha($target,true);
        imagecopyresampled($target,$image,0,0,0,0,$width,$height,$info[0],$info[1]);
        ob_start(); imagewebp($target,null,85); $bytes=ob_get_clean(); imagedestroy($image); imagedestroy($target);
        $path='media/'.Str::uuid().'.webp';
        if (!Storage::disk('public')->put($path,$bytes)) throw ValidationException::withMessages([$field=>'Görsel kaydedilemedi.']);
        return $path;
    }
}
