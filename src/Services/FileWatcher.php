<?php

namespace Muyki\LaravelExcelTranslations\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Muyki\LaravelExcelTranslations\Events\TranslationFileModified;

class FileWatcher
{
    protected const CACHE_KEY = 'excel_translations.file_hashes';

    protected string $watchPath;

    protected array $formats;

    public function __construct()
    {
        $this->watchPath = config('excel-translations.files.path', base_path('lang'));
        $this->formats = config('excel-translations.files.formats', ['csv', 'xls', 'xlsx']);
    }

    public function checkForChanges() : array
    {
        $currentHashes = $this->getCurrentFileHashes();
        $storedHashes = $this->getStoredHashes();

        $changes = [];

        foreach ($currentHashes as $file => $hash) {
            if (!isset($storedHashes[$file])) {
                $changes[] = [
                    'file' => $file,
                    'type' => 'created',
                    'hash' => $hash,
                ];

                $this->fireEvent($file,'created');
            }elseif ($storedHashes[$file] !== $hash) {
                $changes[] = [
                    'file' => $file,
                    'type' => 'updated',
                    'old_hash' => $storedHashes[$file],
                    'new_hash' => $hash
                ];
                $this->fireEvent($file, 'updated');
            }
        }

        foreach ($storedHashes as $file => $hash) {
            if (!isset($currentHashes[$file])) {
                $changes[] = [
                    'file' => $file,
                    'type' => 'deleted',
                    'hash' => $hash
                ];
                $this->fireEvent($file, 'deleted');
            }
        }

        if (!empty($changes)) {
            $this->updateStoredHashes($currentHashes);
        }

        return $changes;
    }

    protected function getCurrentFileHashes(): array
    {
        $hashes = [];

        if (!File::exists($this->watchPath)) {
            return $hashes;
        }

        foreach ($this->formats as $format) {
            $files = File::glob("{$this->watchPath}/*.{$format}");

            foreach ($files as $file) {
                if ($this->isTemporaryFile($file)) {
                    continue;
                }

                $relativePath = str_replace($this->watchPath . '/', '', $file);
                $hashes[$relativePath] = $this->calculateFileHash($file);
            }
        }

        return $hashes;
    }

    protected function calculateFileHash(string $filePath): string
    {
        $stat = stat($filePath);
        return md5($stat['size'] . '_' . $stat['mtime']);
    }

    protected function getStoredHashes(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }


    protected function updateStoredHashes(array $hashes): void
    {
        Cache::put(self::CACHE_KEY, $hashes, now()->addDays(30));
    }


    protected function fireEvent(string $file, string $changeType): void
    {
        $fullPath = $this->watchPath . '/' . $file;

        event(new TranslationFileModified($fullPath, $changeType));

        Log::info("Translation file {$changeType}", [
            'file' => $file,
            'path' => $fullPath
        ]);
    }


    protected function isTemporaryFile(string $filePath): bool
    {
        $basename = basename($filePath);
        $ignorePatterns = config('excel-translations.files.ignore_patterns', ['~$', '.tmp']);

        foreach ($ignorePatterns as $pattern) {
            if (str_contains($basename, $pattern)) {
                return true;
            }
        }

        return false;
    }


    public function reset(): void
    {
        Cache::forget(self::CACHE_KEY);
        Log::info('File watcher hashes reset');
    }


    public function initialize(): void
    {
        $hashes = $this->getCurrentFileHashes();
        $this->updateStoredHashes($hashes);

        Log::info('File watcher initialized', [
            'files_count' => count($hashes),
            'path' => $this->watchPath
        ]);
    }

}
