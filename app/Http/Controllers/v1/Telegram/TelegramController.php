<?php

namespace App\Http\Controllers\v1\Telegram;

use App\Http\Controllers\Controller;

use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Contact;
use App\Models\Message;
use App\Services\Telegram\FileProccessService;
use App\Services\Telegram\TelegramService;
use App\Services\Telegram\BotService;


class TelegramController extends Controller
{
    public function __construct(private BotService $botService, private TelegramService $telegramService, private FileProccessService $fileProccessService)
    {
    }
    // Get current bot detail
    public function getBot()
    {
        $response = $this->botService->show();

        if (!$response) {
            return $this->errorResponse(__('Failed to get bot'), 500);
        }

        return $this->successResponse($response, __('success'), 200);
    }

    // get file from telegram by file_id
    public function getFile($fileId)
    {
        $filePath = $this->fileProccessService->getFilePath($fileId);

        if (!$filePath) {
            return $this->errorResponse(__('File not found'), 404);
        }

        return $this->successResponse([
            'file_path' => $filePath,
        ], __('success'), 200);
    }

    // set webhook url to telegram bot
    public function setWebhook()
    {
        $webhook = Telegram::setWebhook(['url' => env('TELEGRAM_WEBHOOK_URL')]);

        if (!$webhook) {
            return $this->errorResponse(__('Failed to set webhook'), 500);
        }

        return $this->successResponse($webhook, __('success'), 200);
    }


    // Get telegram update maunally
    public function telegramUpdate()
    {
        try {
            Telegram::removeWebhook();

            $update = Telegram::getUpdates();

            if (!$update) {
                return $this->errorResponse(__('no update'), 500);
            }

            foreach ($update as $key => $value) {
                $this->telegramService->webhook($value);
            }

            return $this->successResponse($update, __('success'), 200);
        } catch (\Exception $e) {
            \Log::error('Error in telegram update: ' . $e->getMessage());
            return $this->errorResponse(__($e->getMessage()), 500);
        }
    }

    // Handle telegram webhook
    public function webhook(Request $request): JsonResponse
    {
        // Get update
        $update = Telegram::commandsHandler(true);
        \Log::info('update: ', ['update' => $update]);

        if ($update->isType('message')) {
            $this->telegramService->webhook($update);
        }

        return $this->successResponse($update, __('success'), 200);
    }

    public function getAllContact(): JsonResponse
    {
        $userContact = Contact::all();
        return $this->successResponse($userContact, __('success'), 200);
    }
    public function getAllMessage(Request $request): JsonResponse
    {
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 5);
        
        $messages = Message::latest()
            ->paginate($limit, ['*'], 'page', $page);
            
        return $this->successResponse($messages, __('success'), 200);
    }
}
