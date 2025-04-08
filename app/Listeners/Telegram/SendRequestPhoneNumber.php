<?php

namespace App\Listeners\Telegram;

use App\Events\Telegram\NewUserContact;
use App\Jobs\Telegram\SendRequestPhoneNumberJob;

class SendRequestPhoneNumber
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {

    }

    /**
     * Handle the event.
     */
    public function handle(NewUserContact $event): void
    {
        SendRequestPhoneNumberJob::dispatch($event->chatId)->onQueue('telegram');
    }
}
