<?php

namespace App\Services\Telegram;

use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Contracts\Filesystem\Factory as Storage;

class FileProccessService
{

    public function __construct(protected Storage $storage)
    {
    }

    private function createS3FilePath($userId, $filePath, $folder)
    {
        if (empty($userId) || empty($filePath) || empty($folder)) {
            throw new \InvalidArgumentException('UserId, filePath and folder are required');
        }

        // get folder name, ex: telegram/{userId}/{uniqueId}_{filename}
        $s3FilePath = sprintf(
            'telegram/%s/%s/%s_%s',
            trim($userId),
            trim($folder),
            uniqid(),
            basename(trim($filePath))
        );

        return $s3FilePath;
    }
    // store file in s3
    public function storeFileInS3($fileId, $folder, $userId)
    {
        try {
            // get file path from telegram by file_id
            $filePath = $this->getFilePath($fileId);

            // download file from telegram
            $contents = file_get_contents('https://api.telegram.org/file/bot' . env('TELEGRAM_BOT_TOKEN') . '/' . $filePath);

            // $filename = 'telegram/' . $folder . '/' . uniqid() . '_' . basename($filePath);

            // create s3 file path
            $filename = $this->createS3FilePath($userId, $filePath, $folder);

            // Store on S3 or public disk
            $this->storage->disk('s3')->put($filename, $contents);

            // Get the public URL (S3)
            $url = $this->storage->disk('s3')->url($filename);

            return $url;

        } catch (\Exception $e) {
            \Log::error('Failed to store file in S3', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // get file path from telegram by file_id
    public function getFilePath($fileId)
    {
        $file = Telegram::getFile(['file_id' => $fileId]);

        if (!$file) {
            return false;
        }

        $filePath = $file->getFilePath();
        return $filePath;
    }
}