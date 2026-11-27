<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use ZipArchive;

/**
 * Pure-PHP backup service for shared hosting (no exec(), proc_open(), or mysqldump).
 */
class BackupService
{
    public function backupDirectory(): string
    {
        $dir = storage_path('app/private/backups');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return $dir;
    }

    public function createFullBackup(int $retainLast = 7): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive extension is required for backups.');
        }

        $backupDir = $this->backupDirectory();
        $timestamp = now()->format('Ymd_His');
        $zipPath = $backupDir.DIRECTORY_SEPARATOR."uptoware_backup_{$timestamp}.zip";

        $sqlDump = $this->generateSqlDump();

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Unable to create backup archive at {$zipPath}");
        }

        $zip->addFromString('database.sql', $sqlDump);

        $docsDir = storage_path('app/private/documents');
        if (File::exists($docsDir)) {
            $files = File::allFiles($docsDir);
            foreach ($files as $file) {
                $relativePath = 'documents/'.str_replace('\\', '/', $file->getRelativePathname());
                $zip->addFile($file->getRealPath(), $relativePath);
            }
        }

        $zip->close();
        $this->pruneOldBackups($retainLast);

        return $zipPath;
    }

    public function pruneOldBackups(int $keep = 7): void
    {
        $files = glob($this->backupDirectory().DIRECTORY_SEPARATOR.'uptoware_backup_*.zip') ?: [];
        if (count($files) <= $keep) {
            return;
        }

        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        foreach (array_slice($files, $keep) as $oldFile) {
            @unlink($oldFile);
        }
    }

    public function listBackups(): array
    {
        $files = glob($this->backupDirectory().DIRECTORY_SEPARATOR.'uptoware_backup_*.zip') ?: [];
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return array_map(fn (string $path) => [
            'name' => basename($path),
            'size' => filesize($path) ?: 0,
            'created_at' => date('d M Y, h:i A', filemtime($path) ?: time()),
        ], $files);
    }

    public function generateSqlDump(): string
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $driver = $connection->getDriverName();

        $lines = [
            '-- Uptoware Audit Pure-PHP SQL Backup',
            '-- Generated at: '.now()->toDateTimeString(),
            '-- Driver: '.$driver,
            '',
        ];

        if ($driver === 'mysql') {
            $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
                ->fetchAll(PDO::FETCH_COLUMN);
        }

        foreach ($tables as $table) {
            $safeTable = str_replace('`', '``', (string) $table);
            $lines[] = '';
            $lines[] = "-- Table: {$safeTable}";
            $lines[] = "DROP TABLE IF EXISTS `{$safeTable}`;";

            if ($driver === 'mysql') {
                $createRow = $pdo->query("SHOW CREATE TABLE `{$safeTable}`")->fetch(PDO::FETCH_NUM);
                if (! empty($createRow[1])) {
                    $lines[] = $createRow[1].';';
                }
            } else {
                $stmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?");
                $stmt->execute([$table]);
                $createSql = $stmt->fetchColumn();
                if ($createSql) {
                    $lines[] = $createSql.';';
                }
            }

            $rows = $pdo->query("SELECT * FROM `{$safeTable}`")->fetchAll(PDO::FETCH_ASSOC);
            if (! empty($rows)) {
                $columns = array_map(fn ($col) => '`'.str_replace('`', '``', (string) $col).'`', array_keys($rows[0]));
                $colList = implode(', ', $columns);

                foreach (array_chunk($rows, 100) as $chunk) {
                    $valuesList = [];
                    foreach ($chunk as $row) {
                        $vals = [];
                        foreach ($row as $val) {
                            if ($val === null) {
                                $vals[] = 'NULL';
                            } elseif (is_bool($val)) {
                                $vals[] = $val ? '1' : '0';
                            } elseif (is_int($val) || is_float($val)) {
                                $vals[] = (string) $val;
                            } else {
                                $vals[] = $pdo->quote((string) $val);
                            }
                        }
                        $valuesList[] = '('.implode(', ', $vals).')';
                    }
                    $lines[] = "INSERT INTO `{$safeTable}` ({$colList}) VALUES ".implode(",\n", $valuesList).';';
                }
            }
        }

        if ($driver === 'mysql') {
            $lines[] = '';
            $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
        }

        return implode("\n", $lines)."\n";
    }
}
