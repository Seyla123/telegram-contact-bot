<?php

namespace App\Console\Commands\Telegram;

use App\Events\Telegram\NewUserContact;
use App\Models\Contact;
use App\Services\Telegram\TelegramService;
use Telegram\Bot\Commands\Command;

class StartCommand extends Command
{
    protected string $name = 'start';
    protected string $description = 'Start command to initialize chat';

    public function __construct(private TelegramService $telegramService)
    {
    }
    public function handle()
    {
        $userId = $this->getUpdate()->getMessage()->getFrom()->getId();

        $this->replyWithMessage([
            'text' => 'Hey, there! Welcome to our bot!',
        ]);

        // Check if contact exists with phone number
        $contact = Contact::where('id', $userId)->first();
        \Log::info('this start command log:', ['contact' => $contact]);
        
        if (!$contact || !$contact->phone_number) {
            event(new NewUserContact($userId));
            return;
        }
    }
}