<?php
namespace App\Services\Telegram;

use App\Events\Telegram\NewUserContact;
use App\Models\Contact;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Contracts\Filesystem\Factory as Storage;

class TelegramService
{
    private $chatId;
    private $userId;
    public function __construct(
        protected Storage $storage,
        private FileProccessService $fileProccessService,
    ) {
    }

    public function handle($update)
    {
        $message = $update->getMessage();
        $from = $message->getFrom();
        $this->chatId = $message->getChat()->getId();
        $this->userId = $from->getId();

        if (!$this->chatId) {
            return false;
        }

        // if contact does not exist, create new contact
        $contact = Contact::where('id', $this->chatId)->first();
        \Log::info('contact  : ', ['contact' => $contact]);
        if (!$contact) {
            Contact::create([
                'id' => $this->userId,
                'first_name' => $from->getFirstName(),
                'last_name' => $from->getLastName(),
                'username' => $from->getUsername()
            ]);
            event(new NewUserContact($this->chatId));
        }

        \Log::info('get type ', ['type' => $message->getType()]);

        // Handle phone number sharing
        if ($message->has('contact')) {
            \Log::info('contact shared: ', ['message' => $message]);
            $this->handleContactShared($message);
        }

        if ($message->has('location')) {
            \Log::info('location shared: ', ['message' => $message]);
        }

        if ($message->has('photo')) {
            \Log::info('photo shared: ', ['message' => $message]);
        }

        if ($message->has('voice')) {
            \Log::info('voice shared: ', ['message' => $message]);
        }

        if ($message->has('video')) {
            \Log::info('video shared: ', ['message' => $message]);
        }
        if ($message->has('document')) {
            \Log::info('Document', ['message' => $message]);
        }
        if ($message->has('animation')) {
            \Log::info('animation', ['message' => $message]);
        }
        if ($message->has('audio')) {
            \Log::info('audio', ['message' => $message]);
        }
        if ($message->has('sticker')) {
            \Log::info('sticker', ['message' => $message]);
        }

        // Save message (excluding command)
        if (!str_starts_with($message->getText(), '/')) {

            if ($message->has('photo')) {
                $this->savePhotoMessage($contact, $message->getPhoto(), $message->getCaption(), $message->getDate());
            }
            // save inbound message
            $isSaveSuccess = $this->saveTelegramMessage($this->userId, $message);

            // send message
            if ($isSaveSuccess) {
                Telegram::sendMessage([
                    'chat_id' => $this->chatId,
                    'text' => "Message received \nmessage: " . $message->getText()
                ]);
            }
        }
        Telegram::sendMessage([
            'chat_id' => $this->chatId,
            'text' => json_encode($message, JSON_PRETTY_PRINT)
        ]);
    }

    private function handleContactShared($message)
    {
        $contactData = $message->getContact();
        $userId = $message->getFrom()->getId();

        Contact::updateOrCreate(
            ['id' => $userId],
            [
                'phone_number' => $contactData->getPhoneNumber(),
            ]
        );

        Telegram::sendMessage([
            'chat_id' => $userId,
            'text' => "✅ Thank you for sharing your contact, {$contactData->getFirstName()}!\n" .
                "We've saved your details, and here's what we have:\n" .
                "📞 Phone: {$contactData->getPhoneNumber()}"
        ]);
    }

    private function saveTelegramMessage($userId, $message)
    {
        $contact = Contact::where('id', $userId)->first();

        if (!$contact) {
            \Log::warning("Telegram user ID not found in contacts", ['telegram_user_id' => $userId]);
            return false;
        }
    }


    /**
     * Save photo message from Telegram
     * 
     * @param Contact $contact
     * @param array $photos
     * @param string|null $caption
     * @param string $sentAt
     * @return mixed
     */
    public function savePhotoMessage(Contact $contact, $getPhoto, ?string $caption, string $sentAt)
    {
        if (empty($photos)) {
            return null;
        }

        $photo = $getPhoto->toArray();
        $lastPhoto = end($photo);
        $fileId = $lastPhoto['file_id'];

        // Store the file in S3
        $filePath = $this->fileProccessService->storeFileInS3($fileId, 'photos', $contact->id);

        return $contact->messages()->create([
            'direction' => 'in',
            'message_type' => 'photo',
            'message' => $caption,
            'file_id' => $fileId,
            'file_path' => $filePath,
            'sent_at' => $sentAt,
        ]);
    }

    public function requestPhoneNumber($chatId)
    {
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
            'chat_id' => $chatId,
            'text' => 'សូមចុចប៊ូតុងខាងក្រោមដើម្បីចែករំលែកលេខទូរស័ព្ទរបស់អ្នក៖',
            'reply_markup' => $replyKeyboard
        ]);
    }

}

