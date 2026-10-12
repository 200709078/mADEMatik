<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{PageController,ProjectController,CommentController,ContactController,SeoController};
Route::get('/',[PageController::class,'home'])->name('home');
Route::get('/hakkimda',[PageController::class,'about'])->name('about');
Route::get('/projelerim',[ProjectController::class,'index'])->name('projects.index');
Route::get('/projelerim/{slug}',[ProjectController::class,'show'])->name('projects.show');
Route::get('/yorumlar',[CommentController::class,'index'])->name('comments.index');
Route::get('/saat-sayac',[PageController::class,'clock'])->name('clock');
Route::get('/iletisim',[PageController::class,'contact'])->name('contact');
Route::post('/iletisim',[ContactController::class,'store'])->middleware('throttle:contact')->name('contact.store');
Route::get('/gizlilik',[PageController::class,'legal'])->defaults('document','privacy')->name('legal.privacy');
Route::get('/kullanim-kosullari',[PageController::class,'legal'])->defaults('document','terms')->name('legal.terms');
Route::get('/uyelik-aydinlatma',[PageController::class,'legal'])->defaults('document','membership')->name('legal.membership');
Route::get('/sitemap.xml',[SeoController::class,'sitemap'])->name('sitemap');
Route::get('/robots.txt',[SeoController::class,'robots'])->name('robots');
Route::middleware(['auth','verified','throttle:comments'])->group(function(){
    Route::get('/yorumlar/{comment}/duzenle',[CommentController::class,'edit'])->name('comments.edit');
    Route::post('/yorumlar',[CommentController::class,'store'])->name('comments.store');
    Route::patch('/yorumlar/{comment}',[CommentController::class,'update'])->name('comments.update');
    Route::delete('/yorumlar/{comment}',[CommentController::class,'destroy'])->name('comments.destroy');
    Route::post('/yorumlar/{comment}/sikayet',[CommentController::class,'report'])->name('comments.report');
});
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
