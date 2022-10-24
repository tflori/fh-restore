<?php

namespace tflori\FhRestore;

use DateTime;
use Hugga\Console;

class FileHistory
{
    const DATE_PATTERN = '/ \((\d{4}_\d{2}_\d{2} \d{2}_\d{2}_\d{2}) UTC\)/';
    const DATE_FORMAT = 'Y_m_d H_i_s';

    /** @var Console */
    protected $console;

    /** @var bool */
    protected $dryRun = false;

    /** @var string[] */
    protected $excludes = [];

    public function __construct(Console $console, bool $dryRun = false)
    {
        $this->console = $console;
        $this->dryRun = $dryRun;
    }

    public function exclude(string $pattern)
    {
        $pattern = str_replace([
            '.',
            '*',
        ], [
            '\.',
            '.*',
        ], $pattern);
        $ds = preg_quote(DIRECTORY_SEPARATOR, '#');
        $regex = '#(^|' . $ds . ')' . $pattern . '($|' . $ds . ')#';
        $this->excludes[] = $regex;
    }

    public function restore(string $source, string $target)
    {
        [$files, $subdirectories] = $this->listDirectory($source);

        // recurse into all subdirectories
        foreach ($subdirectories as $subdirectory) {
            $this->restore($subdirectory, $target . DIRECTORY_SEPARATOR . basename($subdirectory));
        }

        // group files by original name
        $files = array_reduce($files, function ($files, $file) {
            if (!preg_match(self::DATE_PATTERN, $file, $match)) {
                return $files;
            }

            $originalName = str_replace($match[0], '', $file);
            $backupTime = DateTime::createFromFormat(self::DATE_FORMAT, $match[1]);

            if ($this->isExcluded($originalName)) {
                return $files;
            }

            if (!isset($files[$originalName])) {
                $files[$originalName] = [];
            }
            $files[$originalName][] = compact('file', 'backupTime');

            return $files;
        }, []);

        foreach ($files as $file => $backups) {
            $targetFile = $target . DIRECTORY_SEPARATOR . basename($file);
            if (file_exists($targetFile)) {
                $this->console->warn($targetFile . ' already exists. skipped');
                continue;
            }

            $this->copy($this->latest($backups), $targetFile);
        }
    }

    protected function listDirectory(string $dir): array
    {
        $dh = opendir($dir);
        $files = [];
        $subdirectories = [];
        while ($file = readdir($dh)) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $subdirectories[] = $path : $files[] = $path;
        }
        return [$files, $subdirectories];
    }

    protected function isExcluded(string $filename): bool
    {
        foreach ($this->excludes as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }
        return false;
    }

    protected function latest(array $backups): string
    {
        if (count($backups) === 1) {
            return $backups[0]['file'];
        }

        usort($backups, function ($a, $b) {
            return $a['backupTime']->getTimestamp() - $b['backupTime']->getTimestamp();
        });

        return array_pop($backups)['file'];
    }

    protected function copy(string $sourceFile, string $targetFile)
    {
        $this->console->info(
            'restoring ' . $targetFile . ' from ' . $sourceFile,
            $this->dryRun ? Console::WEIGHT_NORMAL : Console::WEIGHT_LOWER
        );
        if ($this->dryRun) {
            return;
        }

        $targetDirectory = dirname($targetFile);
        if (!file_exists($targetDirectory)) {
            if (!mkdir($targetDirectory, 0777, true)) {
                $this->console->error('unable to create target directory ' . $targetDirectory);
                exit(2);
            }
        } elseif (!is_dir($targetDirectory)) {
            $this->console->error('target directory is not a directory');
            exit(2);
        }

        copy($sourceFile, $targetFile);
    }
}
