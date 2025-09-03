<?php

namespace Muyki\LaravelExcelTranslations\Console\Commands;

use Illuminate\Console\Command;
use Muyki\LaravelExcelTranslations\Services\FileWatcher;

class WatchTranslationFilesCommand extends Command
{
    protected $signature = 'excel-translations:watch
                            {--interval=5 : Check interval in seconds}
                            {--once : Run once and exit}';

    protected $description = 'Watch translation files for changes and clear cache automatically';

    protected FileWatcher $watcher;

    public function __construct(FileWatcher $watcher)
    {
        parent::__construct();
        $this->watcher = $watcher;
    }

    public function handle(): int
    {
        $interval = (int) $this->option('interval');
        $runOnce = $this->option('once');

        $this->info('🔍 Watching translation files for changes...');
        $this->info("Path: " . config('excel-translations.files.path'));
        $this->info("Check interval: {$interval} seconds");

        if ($runOnce) {
            return $this->checkOnce();
        }

        return $this->watchContinuously($interval);
    }

    protected function checkOnce(): int
    {
        $changes = $this->watcher->checkForChanges();

        if (empty($changes)) {
            $this->info('✅ No changes detected.');
            return 0;
        }

        $this->displayChanges($changes);
        return 0;
    }


    protected function watchContinuously(int $interval): int
    {
        // İlk başlatma
        $this->watcher->initialize();
        $this->info('📡 File watcher initialized. Press Ctrl+C to stop.');

        $lastCheck = time();
        $checkCount = 0;

        // Sürekli döngü
        while (true) {
            sleep($interval);
            $checkCount++;

            // Her 12 kontrolde bir durum göster (1 dakika @ 5 saniye interval)
            if ($checkCount % 12 === 0) {
                $uptime = $this->formatUptime(time() - $lastCheck);
                $this->line("⏱ Running for {$uptime}...");
            }

            // Değişiklikleri kontrol et
            $changes = $this->watcher->checkForChanges();

            if (!empty($changes)) {
                $this->newLine();
                $this->warn('Changes detected!');
                $this->displayChanges($changes);
                $this->info('Cache cleared automatically.');
                $this->newLine();
            }

            if ($checkCount % 100 === 0) {
                gc_collect_cycles();
            }
        }

        return 0;
    }


    protected function displayChanges(array $changes): void
    {
        $table = [];

        foreach ($changes as $change) {
            $emoji = match($change['type']) {
                'created' => '</added>',
                'updated' => '</updated>',
                'deleted' => '</deleted>',
                default => '?'
            };

            $table[] = [
                $emoji . ' ' . $change['type'],
                $change['file'],
                $change['hash'] ?? $change['new_hash'] ?? '-'
            ];
        }

        $this->table(['Type', 'File', 'Hash'], $table);
    }


    protected function formatUptime(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $seconds);
        } else {
            return sprintf('%ds', $seconds);
        }
    }
}
