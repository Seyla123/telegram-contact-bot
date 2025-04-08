<?php

namespace App\Jobs\Telegram;

use App\Jobs\Job;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Keyboard\Keyboard;
use Throwable;

class SendRequestPhoneNumberJob extends Job
{
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(private $chatId, )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        \Log::info('SendRequestPhoneNumberJob: ', ['chatId' => $this->chatId]);
        // Create reply keyboard for the share contact button
        $replyKeyboard = Keyboard::make()
            ->row([
                Keyboard::button([
                    'text' => '📱 Share Phone Number',
                    'request_contact' => true
                ])
            ])
            ->setResizeKeyboard(true)
            ->setOneTimeKeyboard(true);

        // Then send the share contact button as a separate message
        Telegram::sendMessage([
            'chat_id' => $this->chatId,
            'text' => 'សូមចុចប៊ូតុងខាងក្រោមដើម្បីចែករំលែកលេខទូរស័ព្ទរបស់អ្នក៖',
            'reply_markup' => $replyKeyboard
        ]);
    }
    public function failed(Throwable $exception): void
    {
        \Log::error("Failed to Send Request PhoneNumber to user, chat id {$this->chatId}", [
            'error' => $exception->getMessage()
        ]);
    }
}
