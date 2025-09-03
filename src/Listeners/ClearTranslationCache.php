<?php

namespace Muyki\LaravelExcelTranslations\Listeners;

use Illuminate\Support\Facades\Log;
use Muyki\LaravelExcelTranslations\Contracts\TranslationRepositoryInterface;
use Muyki\LaravelExcelTranslations\Events\TranslationFileModified;

class ClearTranslationCache
{
    protected TranslationRepositoryInterface $repository;

    public function __construct(TranslationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function handle(TranslationFileModified $event): void
    {
        Log::info('Translation file modified, clearing cache', [
            'file' => $event->fileName,
            'path' => $event->filePath,
            'change_type' => $event->changeType,
        ]);

        $this->repository->refresh();

        $this->notifyIfEnabled($event);
    }

    protected function notifyIfEnabled(TranslationFileModified $event): void
    {
        if(config('excel-translations.events.notify_on_change', false)) {
            Log::info('Translation file change notification', [
                'file' => $event->fileName,
                'type' => $event->changeType
            ]);
        }
    }

}
