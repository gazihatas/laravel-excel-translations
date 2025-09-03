<?php

namespace Muyki\LaravelExcelTranslations\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Muyki\LaravelExcelTranslations\Services\FileWatcher;
use Illuminate\Support\Facades\Cache;

class CheckTranslationFiles
{
    protected FileWatcher $watcher;

    protected const LAST_CHECK_KEY = 'excel_translations.last_check';
    protected int $checkInterval;

    public function __construct(FileWatcher $watcher)
    {
        $this->watcher = $watcher;
        $this->checkInterval = config('excel-translations.events.check_interval', 60);
    }

    /**
     * Handle request
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->shouldCheck()) {
            $this->checkFiles();
        }

        return $next($request);
    }

    /**
     * Kontrol yapılmalı mı?
     */
    protected function shouldCheck(): bool
    {

        if (!config('excel-translations.events.auto_check', false)) {
            return false;
        }

        if (app()->environment('production')) {
            return false;
        }


        $lastCheck = Cache::get(self::LAST_CHECK_KEY, 0);
        $now = time();

        if (($now - $lastCheck) < $this->checkInterval) {
            return false;
        }

        return true;
    }


    protected function checkFiles(): void
    {
        $this->watcher->checkForChanges();
        Cache::put(self::LAST_CHECK_KEY, time(), now()->addHours(1));
    }
}
