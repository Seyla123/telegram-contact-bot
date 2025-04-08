<?php
namespace App\Services\Telegram;

use App\Models\Contact;
use Telegram\Bot\Api;

class BotService
{
    protected $telegram;

    /**
     * Create a new instance.
     *
     * @param  Api  $telegram
     */
    public function __construct(Api $telegram)
    {
        $this->telegram = $telegram;
    }

    private function saveMediaMessage($userId, $type, $fileId)
    {
        $contact = Contact::find($userId)->first();

        if ($contact) {
            return $contact->messages()->create([
                'message_type' => $type,
                'file_id' => $fileId,
                'direction' => 'in',
            ]);

        }
        
        return false;
    }


    /**
     * Show the bot information.
     */
    public function show()
    {
        $response = $this->telegram->getMe();

        return $response;
    }
}

