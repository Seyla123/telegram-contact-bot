<?php
namespace App\Services\Telegram;

use App\Events\Telegram\NewUserContact;
use App\Models\Contact;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Contracts\Filesystem\Factory as Storage;

class TelegramService
{
    private $chatId;
    private $userId;
    private $contact;
    public function __construct(
        protected Storage $storage,
        private FileProccessService $fileProccessService,
    ) {
    }

    public function webhook($update)
    {
        $message = $update->getMessage();
        $from = $message->getFrom();
        $this->chatId = $message->getChat()->getId();
        $this->userId = $from->getId();

        if (!$this->chatId) {
            return false;
        }

        // if contact does not exist, create new contact
        $this->contact = Contact::where('id', $this->chatId)->first();
        \Log::info('contact  : ', ['contact' => $this->contact]);
        if (!$this->contact) {
            $this->contact = Contact::create([
                'id' => $this->userId,
                'first_name' => $from->getFirstName(),
                'last_name' => $from->getLastName(),
                'username' => $from->getUsername()
            ]);
            event(new NewUserContact($this->chatId));
        }

        \Log::info('get type ', ['type' => $message->getType()]);

        $this->handleMessageType($message);

        return true;
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

    private function handlePhotoMessage($message)
    {
        $photos = $message->getPhoto();
        $caption = $message->getCaption();
        $sentAt = $message->getDate();

        \Log::info('handlePhoto', ['photos' => $photos, 'caption' => $caption, 'sentAt' => $sentAt]);
        // Save the photo message
        $photo = $this->savePhotoMessage($this->contact, $photos, $caption, $sentAt);

        \Log::info('photo: ', ['photo' => $photo]);

        return $photo;

    }

    private function handleTextMessage($message)
    {
        $text = $message->getText();
        $sentAt = $message->getDate();
        return $this->saveTextMessage($this->contact, $text, $sentAt);
    }

    /**
     * Save text message from Telegram
     */
    private function saveTextMessage(Contact $contact, $text, $sentAt)
    {
        return $contact->messages()->create([
            'direction' => 'in',
            'message_type' => 'text',
            'message' => $text,
            'sent_at' => $sentAt,
        ]);
    }
    /**
     * Save photo message from Telegram
     */
    private function savePhotoMessage(Contact $contact, $getPhoto, ?string $caption, string $sentAt)
    {
        try {
            if (empty($getPhoto)) {
                return null;
            }

            $photo = $getPhoto->toArray();
            $lastPhoto = end($photo);
            $fileId = $lastPhoto['file_id'];

            // Store the file in S3
            $filePath = $this->fileProccessService->storeFileInS3($fileId, 'photos', $contact->id);
            \Log::info('filePath: ', ['filePath' => $filePath]);
            if (empty($filePath)) {
                return null;
            }
            // Create a message record
            return $contact->messages()->create([
                'direction' => 'in',
                'message_type' => 'photo',
                'message' => $caption,
                'file_id' => $fileId,
                'file_path' => $filePath,
                'sent_at' => $sentAt,
            ]);
        } catch (\Throwable $th) {
            \Log::warning('error in save photo message', ['error' => $th]);
            throw $th;
        }
    }

    private function handleVoiceMessage($message)
    {
        $voice = $message->getVoice();
        $sentAt = $message->getDate();

        \Log::info('handleVoice', context: ['voice' => $voice, 'sentAt' => $sentAt]);

        return $this->saveVoiceMessage($this->contact, $voice, $sentAt);
    }
    /**
     * Save voice message from Telegram
     */
    private function saveVoiceMessage(Contact $contact, $voice, string $sentAt)
    {
        try {
            if (empty($voice)) {
                return null;
            }

            $voiceData = $voice->toArray();
            $fileId = $voiceData['file_id'];

            // Store the file in S3
            $filePath = $this->fileProccessService->storeFileInS3($fileId, 'voices', $contact->id);
            \Log::info('voiceFilePath: ', ['filePath' => $filePath]);

            if (empty($filePath)) {
                return null;
            }

            // Create a message record
            return $contact->messages()->create([
                'direction' => 'in',
                'message_type' => 'voice',
                'file_id' => $fileId,
                'file_path' => $filePath,
                'sent_at' => $sentAt,
                'duration' => $voiceData['duration'] ?? null,
            ]);
        } catch (\Throwable $th) {
            \Log::warning('error in save voice message', ['error' => $th]);
            throw $th;
        }
    }


    // Update the handle method to call handleVoiceMessage
    public function handleMessageType($message)
    {

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
            $this->handlePhotoMessage($message);
        }

        if ($message->has('voice')) {
            \Log::info('voice shared: ', ['message' => $message]);
            $this->handleVoiceMessage($message);  // Changed from just logging to actual handling
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
        if (!str_starts_with($message->getText(), '/') && $message->has('text')) {
            // save inbound message
            $this->handleTextMessage($message);
        }

        Telegram::sendMessage([
            'chat_id' => $this->chatId,
            'text' => json_encode($message, JSON_PRETTY_PRINT)
        ]);

        return true;
    }
}

