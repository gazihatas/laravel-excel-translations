<?php

namespace Muyki\LaravelExcelTranslations\Events;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranslationFileModified
{
    use Dispatchable, SerializesModels;

    public string $filePath;

    public string $changeType;

    public string $fileName;

    public int $timestamp;

    public function __construct(string $filePath, string $changeType = 'updated')
    {
        $this->filePath = $filePath;
        $this->changeType = $changeType;
        $this->fileName = pathinfo($filePath, PATHINFO_FILENAME);
        $this->timestamp = time();
    }

}
