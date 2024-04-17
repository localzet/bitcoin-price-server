<?php

use App\Http\Controllers\BitcoinController;
use App\Http\Controllers\UserController;
use App\Mail\BitcoinPriceChanged;
use App\Models\BitcoinPrice;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/user/{id}', [UserController::class, 'show']);
Route::post('/user', [UserController::class, 'store']);

Route::get('/bitcoin/{user_id}', [BitcoinController::class, 'show']);

Route::get('/mail/{user_id}', function ($user_id) {
    $user = User::find($user_id);

    $latest = BitcoinPrice::latest()->first();
    $latestPrice = $latest ? $latest->price : null;

    Mail::to($user->email)->queue(new BitcoinPriceChanged($latestPrice));

    return response($user->email);
});
