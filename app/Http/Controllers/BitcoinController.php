<?php

namespace App\Http\Controllers;

use App\Mail\BitcoinPriceChanged;
use App\Models\BitcoinPrice;
use App\Models\User;
use App\WebSocket\SocketController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

class BitcoinController extends Controller
{
    public function show($user_id)
    {
        $response = Http::get('https://min-api.cryptocompare.com/data/price?fsym=BTC&tsyms=USD');
        $price = $response->json()['USD'];

        $latest = BitcoinPrice::latest()->first();
        $latestPrice = $latest ? $latest->price : null;

        $user = User::find((int)$user_id);
        $message = json_encode(['price' => $price]);

        $force = request()->input('force') == 'true';

        if ($user && (($latestPrice !== null && abs($latestPrice - $price) >= 100) || $force)) {
            if (Redis::sismember('active_users', (int)$user_id)) {
                ignore_user_abort(true);
                set_time_limit(0);

                ob_start();
                echo 'Отправил в сессию (' . $message . ')';
                header('Connection: close');
                header('Content-Length: ' . ob_get_length());
                ob_end_flush();
                ob_flush();
                flush();

                SocketController::emit($user_id, 'price', $latestPrice);
            } else {
                Mail::to($user->email)->queue(new BitcoinPriceChanged($price));
                return response('Отправил письмо на ' . $user->email . ' (' . $message . ')');
            }
        }

        return response('Изменения незначительны, никого не беспокоил (' . $message . ')');
    }
}
