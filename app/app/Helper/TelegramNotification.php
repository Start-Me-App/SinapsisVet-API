<?php

declare(strict_types=1);

namespace App\Helper;

use NotificationChannels\Telegram\TelegramMessage;

use Telegram\Bot\Api;

use App\Support\TokenManager;

class TelegramNotification
{

    protected $telegram;

    public function __construct()
    {
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
    }

    public function toTelegram($body)
    {
        $user_id = null;
        $accessToken = TokenManager::getTokenFromRequest();
        if($accessToken){
        $user = TokenManager::getUserFromToken($accessToken);
        $user_id = $user->id;
        }

        #get url of the request
        $url = url()->current();

        $text = "Usuario: ".$user_id."\nException:".$body."\nURL: ".$url;

        $this->telegram->sendMessage([
            'chat_id' => env('TELEGRAM_CHAT_ID_TETU'),
            'text' => $text
        ]);

        $this->telegram->sendMessage([
            'chat_id' => env('TELEGRAM_CHAT_ID_FETA'),
            'text' => $text
        ]);
        

        $this->telegram->sendMessage([
            'chat_id' => env('TELEGRAM_CHAT_ID_CIRO'),
            'text' => $body
        ]);
        
        
    }

}
