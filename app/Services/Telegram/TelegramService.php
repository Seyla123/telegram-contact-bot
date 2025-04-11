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

    private function handlePhotoMessage($message, $direction)
    {
        $photos = $message->getPhoto();
        $caption = $message->getCaption();
        $sentAt = $message->getDate();

        \Log::info('handlePhoto', ['photos' => $photos, 'caption' => $caption, 'sentAt' => $sentAt]);
        // Save the photo message
        $photo = $this->savePhotoMessage($this->contact, $photos, $caption, $sentAt, $direction);

        \Log::info('photo: ', ['photo' => $photo]);

        return $photo;

    }

    private function handleTextMessage($message, $direction)
    {
        $text = $message->getText();
        $sentAt = $message->getDate();
        return $this->saveTextMessage($this->contact, $text, $sentAt, $direction);
    }

    /**
     * Save text message from Telegram
     */
    private function saveTextMessage(Contact $contact, $text, $sentAt, $direction)
    {
        return $contact->messages()->create([
            'direction' => $direction,
            'message_type' => 'text',
            'message' => $text,
            'sent_at' => $sentAt,
        ]);
    }
    /**
     * Save photo message from Telegram
     */
    private function savePhotoMessage(Contact $contact, $getPhoto, ?string $caption, string $sentAt, $direction)
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
                'direction' => $direction,
                'message_type' => 'photo',
                'message' => $caption,
                'file_id' => $fileId,
                'file_path' => $filePath,
                'sent_at' => $sentAt,
                'width' => $lastPhoto['width'],
                'height' => $lastPhoto['height'],
                'file_size' => $lastPhoto['file_size'],
            ]);
        } catch (\Throwable $th) {
            \Log::warning('error in save photo message', ['error' => $th]);
            throw $th;
        }
    }

    private function handleVoiceMessage($message, $direction)
    {
        $voice = $message->getVoice();
        $sentAt = $message->getDate();

        \Log::info('handleVoice', context: ['voice' => $voice, 'sentAt' => $sentAt]);

        return $this->saveVoiceMessage($this->contact, $voice, $sentAt, $direction);
    }
    /**
     * Save voice message from Telegram
     */
    private function saveVoiceMessage(Contact $contact, $voice, string $sentAt, $direction)
    {
        try {
            if (empty($voice)) {
                return null;
            }

            $voiceData = $voice->toArray();
            $fileId = $voiceData['file_id'];

            // Store the file in S3
            $filePath = $this->fileProccessService->storeFileInS3($fileId, 'voices', $contact->id);
            \Log::info('voiceFilePath: ', ['voiceData' => $voiceData['duration']]);

            if (empty($filePath)) {
                return null;
            }

            // Create a message record
            return $contact->messages()->create([
                'direction' => $direction,
                'message_type' => 'voice',
                'file_id' => $fileId,
                'file_path' => $filePath,
                'sent_at' => $sentAt,
                'duration' => $voiceData['duration'] ?? null,
                'mime_type' => $voiceData['mime_type'] ?? null
            ]);
        } catch (\Throwable $th) {
            \Log::warning('error in save voice message', ['error' => $th]);
            throw $th;
        }
    }


    private function handleDocumentMessage($message, $direction)
    {
        $document = $message->getDocument();
        $caption = $message->getCaption();
        $sentAt = $message->getDate();

        \Log::info('handleDocument', ['document' => $document, 'caption' => $caption, 'sentAt' => $sentAt]);

        return $this->saveDocumentMessage($this->contact, $document, $caption, $sentAt, $direction);
    }

    /**
     * Save document message from Telegram
     */
    private function saveDocumentMessage(Contact $contact, $document, ?string $caption, string $sentAt, $direction)
    {
        try {
            if (empty($document)) {
                return null;
            }

            $documentData = $document->toArray();
            $fileId = $documentData['file_id'];

            // Store the file in S3
            $filePath = $this->fileProccessService->storeFileInS3($fileId, 'documents', $contact->id);
            \Log::info('documentFilePath: ', ['filename' => $documentData['file_name']]);

            if (empty($filePath)) {
                return null;
            }

            // Create a message record
            return $contact->messages()->create([
                'direction' => $direction,
                'message_type' => 'document',
                'message' => $caption,
                'file_id' => $fileId,
                'file_path' => $filePath,
                'file_name' => $documentData['file_name'],
                'file_size' => $documentData['file_size'] ?? null,
                'mime_type' => $documentData['mime_type'] ?? null,
                'sent_at' => $sentAt,
            ]);
        } catch (\Throwable $th) {
            \Log::warning('error in save document message', ['error' => $th]);
            throw $th;
        }
    }

    private function handleAnimationMessage($message, $direction)
    {
        $animation = $message->getAnimation();
        $caption = $message->getCaption();
        $sentAt = $message->getDate();

        \Log::info('handleAnimation', ['animation' => $animation, 'caption' => $caption, 'sentAt' => $sentAt]);

        return $this->saveAnimationMessage($this->contact, $animation, $caption, $sentAt, $direction);
    }

    /**
     * Save animation message from Telegram
     */
    private function saveAnimationMessage(Contact $contact, $animation, ?string $caption, string $sentAt, $direction)
    {
        try {
            if (empty($animation)) {
                return null;
            }

            $animationData = $animation->toArray();
            $fileId = $animationData['file_id'];

            // Store the file in S3
            $filePath = $this->fileProccessService->storeFileInS3($fileId, 'animations', $contact->id);
            \Log::info('animationFilePath: ', ['filePath' => $filePath]);

            if (empty($filePath)) {
                return null;
            }

            // Create a message record
            return $contact->messages()->create([
                'direction' => 'in',
                'message_type' => 'animation',
                'message' => $caption,
                'file_id' => $fileId,
                'file_path' => $filePath,
                'file_name' => $animationData['file_name'] ?? null,
                'mime_type' => $animationData['mime_type'] ?? null,
                'file_size' => $animationData['file_size'] ?? null,
                'width' => $animationData['width'] ?? null,
                'height' => $animationData['height'] ?? null,
                'duration' => $animationData['duration'] ?? null,
                'sent_at' => $sentAt,
            ]);
        } catch (\Throwable $th) {
            \Log::warning('error in save animation message', ['error' => $th]);
            throw $th;
        }
    }

    // Update the handleMessageType method to call handleAnimationMessage
    public function handleMessageType($message, $direction = "in")
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
            $this->handlePhotoMessage($message, $direction);
        }

        if ($message->has('voice')) {
            \Log::info('voice shared: ', ['message' => $message]);
            $this->handleVoiceMessage($message, $direction);  // Changed from just logging to actual handling
        }

        if ($message->has('document') & !$message->has('animation')) {
            \Log::info('Document shared: ', ['message' => $message]);
            $this->handleDocumentMessage($message, $direction);  // Changed from just logging to actual handling
        }

        if ($message->has('animation')) {
            \Log::info('Animation shared: ', ['message' => $message]);
            $this->handleAnimationMessage($message, $direction);  // Changed from just logging to actual handling
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
            $this->handleTextMessage($message, $direction);
        }

        // Telegram::sendMessage([
        //     'chat_id' => $this->chatId,
        //     'text' => json_encode($message, JSON_PRETTY_PRINT)
        // ]);

        return true;
    }

    // handle send message to telegram
    public function handleSendMessageType($message, $chat_id)
    {
        $this->contact = Contact::find($chat_id)->first();
        $this->chatId = $chat_id;
        $this->userId = $chat_id;

        if (!$this->contact) {
            throw new \Exception('Contact not found');
        }
        // return $this->handlePhotoMessage($message, 'out');
        return $this->handleMessageType($message, 'out');
    }


}

