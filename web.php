<?php
use Illuminate\Support\Facades\Route;
Route::any('/', fn() => require base_path('app/Legacy/indexm.php'));
Route::any('/indexm.php', fn() => require base_path('app/Legacy/indexm.php'));
Route::any('/player2.php', fn() => require base_path('app/Legacy/player2.php'));
Route::any('/player.php', fn() => require base_path('app/Legacy/player.php'));
