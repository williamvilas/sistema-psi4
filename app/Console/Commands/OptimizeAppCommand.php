<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class OptimizeAppCommand extends Command
{
    protected $signature = 'app:optimize-app-command';
    protected $description = 'Clean and optimize application for production';

    public function handle()
    {
        $this->info('Starting optimization process');

        // clear caches
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        $this->info('Caches cleared');

        // rebuild caches
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        $this->info('Caches rebuilt');

        // remove large log files
        $logPath = storage_path('logs');

        if (File::exists($logPath)) {
            foreach (File::files($logPath) as $file) {
                File::delete($file);
                $this->info('Deleted log: '.$file->getFilename());
            }
        }

        // clear temp cache files
        $tempPath = storage_path('framework/cache/data');

        if (File::exists($tempPath)) {
            File::cleanDirectory($tempPath);
            $this->info('Temp cache cleared');
        }

        // optimize composer autoload
        exec('composer dump-autoload -o');

        $this->info('Autoload optimized');

        // clear session files
        $sessionPath = storage_path('framework/sessions');

        if (File::exists($sessionPath)) {
            File::cleanDirectory($sessionPath);
            $this->info('Sessions cleared');
        }

        $this->info('Optimization completed successfully');

        return Command::SUCCESS;
    }
}
