<?php

namespace App\Http\Controllers\v1\Telegram;

use App\Http\Controllers\Controller;

use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Contact;
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
                $this->telegramService->handle($value);
            }

            return $this->successResponse($update, __('success'), 200);
        } catch (\Exception $e) {
            \Log::error('Error in telegramUpdate: ' . $e->getMessage());
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
            $this->telegramService->handle($update);
        }

        return $this->successResponse($update, __('success'), 200);
    }

    public function getAllContact(): JsonResponse
    {
        $userContact = Contact::all();
        return $this->successResponse($userContact, __('success'), 200);
    }
}
