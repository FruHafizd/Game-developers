<?php

use App\Http\Controllers\Authentication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('v1/auth/signup',[Authentication::class,'signup']);
Route::post('v1/auth/signin',[Authentication::class,'signin']);
Route::post('v1/auth/signout',[Authentication::class,'signout'])->middleware('auth:sanctum');

Route::middleware(['auth:sanctum','is_admin'])->group(function ()  {
    Route::get('v1/admins',[Authentication::class,'getAllAdmins']);
    Route::post('v1/users',[Authentication::class,'createUser']);
    Route::get('v1/users',[Authentication::class,'getAllUser']);
});