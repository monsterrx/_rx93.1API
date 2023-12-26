<?php

use App\Http\Controllers\API\MobileAppController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/', [MobileAppController::class, 'home'])->name('home');
Route::get('/assets/{id}', [MobileAppController::class, 'assets'])->name('dynamic.assets');

Route::get('/live', [MobileAppController::class, 'live'])->name('live');

Route::get('/charts', [MobileAppController::class, 'charts'])->name('charts');
Route::get('/charts/vote', [MobileAppController::class, 'vote'])->name('vote');

Route::get('/articles', [MobileAppController::class, 'articles'])->name('articles');
Route::get('/articles/{id}', [MobileAppController::class, 'viewArticle'])->name('view.article');

Route::get('/podcasts', [MobileAppController::class, 'podcasts'])->name('podcasts');
Route::get('/podcasts/{id}', [MobileAppController::class, 'viewPodcast'])->name('view.podcast');

Route::get('/youtube/{max}', [MobileAppController::class, 'youTube'])->name('youtube');

Route::get('/search', [MobileAppController::class, 'search'])->name('search');
Route::get('/browse', [MobileAppController::class, 'browse'])->name('browse');
Route::get('/help', [MobileAppController::class, 'help'])->name('help');
Route::get('/about', [MobileAppController::class, 'about'])->name('about');
