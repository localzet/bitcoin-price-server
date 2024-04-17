<?php

namespace App\Console\Commands;

use App\Mail\BitcoinPriceChanged;
use Illuminate\Console\Command;
use App\WebSocket\SocketController;
use App\Models\BitcoinPrice;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

class UpdateBitcoinPrice extends Command
{
    protected $signature = 'update:bitcoin-price';

    public function handle()
    {
        $response = Http::get('https://min-api.cryptocompare.com/data/price?fsym=BTC&tsyms=USD');
        $price = $response->json()['USD'];

        $latest = BitcoinPrice::latest()->first();
        $latestPrice = $latest ? $latest->price : null;

        BitcoinPrice::create(['price' => $price]);

        if ($latestPrice !== null && abs($latestPrice - $price) >= 100) {
            $users = User::all();
            foreach ($users as $user) {
                if (Redis::sismember('active_users', $user->id)) {
                    SocketController::emit($user->id, 'price', $price);
                } else {
                    Mail::to($user->email)->queue(new BitcoinPriceChanged($price));
                }
            }
        }
    }
}
