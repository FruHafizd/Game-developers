<?php

use App\Http\Controllers\Authentication;
use App\Http\Controllers\GamesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('v1/auth/signup',[Authentication::class,'signup']);
Route::post('v1/auth/signin',[Authentication::class,'signin']);
Route::post('v1/auth/signout',[Authentication::class,'signout'])->middleware('auth:sanctum');
Route::get('v1/games',[GamesController::class,'listGames']);
Route::post('v1/games',[GamesController::class,'createGame'])->middleware('auth:sanctum');
Route::get('v1/games/{slug}',[GamesController::class,'getDetailGame']);
Route::post('v1/games/{slug}/upload',[GamesController::class,'uploadGameVersion'])->middleware('auth:sanctum');
Route::put('v1/games/{slug}',[GamesController::class,'updateGame'])->middleware('auth:sanctum');
Route::delete('v1/games/{slug}',[GamesController::class,'deleteGame'])->middleware('auth:sanctum');
// Route::get('/games/{slug}/{version}/{file?}', [GamesController::class, 'serveGameFile'])
//      ->where('version', '\d+') // Version harus angka
//      ->where('file', '.*'); // File boleh mengandung titik (untuk ekstensi)

Route::get('v1/users/{username}', [Authentication::class, 'getUserDetails']);

Route::middleware(['auth:sanctum','is_admin'])->group(function ()  {
    Route::get('v1/admins',[Authentication::class,'getAllAdmins']);
    Route::post('v1/users',[Authentication::class,'createUser']);
    Route::get('v1/users',[Authentication::class,'getAllUser']);
    Route::put('v1/users/{id}',[Authentication::class,'updateUser']);
    Route::delete('v1/users/{id}',[Authentication::class,'deleteUser']);
});