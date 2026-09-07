<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramController extends Controller
{

    public function sendMessage($chatId, $text, $replyMarkup = null)
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $url = "https://api.telegram.org/bot$token/sendMessage";
    
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        

       if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        Http::post($url, $params);
    }

    public function obHavo($chatId, $city, $mainMenu = null)
    {
        $apiKey = env('OPENWEATHER_API_KEY');
        $url = "https://api.openweathermap.org/data/2.5/weather?q={$city}&appid={$apiKey}&units=metric&lang=uz";

        $response = Http::get($url);

        if ($response->successful()) {
            $data = $response->json();

            $shahar = $data['name'] ?? $city;
            $harorat = round($data['main']['temp']);

            // OpenWeather til sozlamalari bilan ishlash uchun mb_ucfirst usuli:
            $rawTavsif = $data['weather'][0]['description'] ?? '';
            $tavsif = mb_strtoupper(mb_substr($rawTavsif, 0, 1)) . mb_substr($rawTavsif, 1);

            $namlik = $data['main']['humidity'];
            $shamol = $data['wind']['speed'];

            $message = "🌤 <b>{$shahar} shahridagi ob-havo:</b>\n\n"
                . "🌡 Harorat: <b>{$harorat}°C</b>\n"
                . "🌈 Holat: <b>{$tavsif}</b>\n"
                . "💧 Namlik: <b>{$namlik}%</b>\n"
                . "💨 Shamol tezligi: <b>{$shamol} m/s</b>";

            $this->sendMessage($chatId, $message, $mainMenu);
        } else {
            $this->sendMessage($chatId, "⚠️ <b>{$city}</b> shahri topilmadi.", $mainMenu);
        }
    }

    public function handle(Request $request)
    {
        $chatId = $request->input('message.chat.id');
        $text = trim($request->input('message.text'));
        $mainMenu = [
            'keyboard' => [
                [
                    ['text' => '💰 Valyuta kurslari'],
                    ['text' => '🌤 Ob-havo']
                ]
            ],
            'resize_keyboard' => true,
            'persistent' => true,
        ];

        if ($text === '/start') {
            $this->sendMessage($chatId, 'Botga xush kelibsiz! ' . 'Valyuta kurslarini bilish uchun /kurs buyrug\'ini yuboring.', $mainMenu);
        } elseif ($text === '/kurs' || $text === '💰 Valyuta kurslari') {

            $response = Http::get('https://cbu.uz/uz/arkhiv-kursov-valyut/json/');
            $valyutalar = $response->json();

            $usd = collect($valyutalar)->firstWhere('Ccy', 'USD');
            $eur = collect($valyutalar)->firstWhere('Ccy', 'EUR');
            $rub = collect($valyutalar)->firstWhere('Ccy', 'RUB');

            $message = "Bugungi kundagi valyuta kurslari:\n"
                . "🇺🇸 1 USD = " . $usd['Rate'] . " so'm\n"
                . "🇪🇺 1 EUR = " . $eur['Rate'] . " so'm\n"
                . "🇷🇺 1 RUB = " . $rub['Rate'] . " so'm";

            $this->sendMessage($chatId, $message, $mainMenu);
        } elseif (str_starts_with($text, '/obhavo') || $text === '🌤 Ob-havo') {
            $parts = explode(' ', $text, 2);

           if ($text === '🌤 Ob-havo' || $text === '/obhavo') {
                $city = 'Tashkent';
                $this->sendMessage($chatId, "Iltimos, shahar nomini kiriting. Masalan: /obhavo Tashkent", $mainMenu);
            } else {
                $parts = explode(' ', $text, 2);
                $city = (isset($parts[1]) && !empty(trim($parts[1]))) ? trim($parts[1]) : 'Tashkent';
            }
            $this->obHavo($chatId, $city, $mainMenu);

        } else {
            $this->sendMessage($chatId, 'Kechirasiz, noto\'g\'ri buyruq. Iltimos, /start yoki /kurs buyrug\'ini yuboring.', $mainMenu);
        }


    }
}
