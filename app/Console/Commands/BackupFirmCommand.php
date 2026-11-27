<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupFirmCommand extends Command
{
    protected $signature = 'app:backup-firm {--keep=7 : Number of recent backups to retain}';

    protected $description = 'Create a pure-PHP database + documents zip backup and prune old archives.';

    public function handle(BackupService $backupService): int
    {
        $path = $backupService->createFullBackup((int) $this->option('keep'));
        $this->info('Backup created: '.basename($path));

        return self::SUCCESS;
    }
}
