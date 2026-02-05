<?php
declare(strict_types=1);

namespace HyperCtl\Watcher;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class SimpleFileWatcher implements FileWatcherInterface
{
    private array $directories;
    private array $files;
    private array $extensions;
    private array $fileSignatures = [];

    public function __construct(
        array $directories,
        array $files,
        array $extensions,
    ) {
        $this->directories = $directories;
        $this->files = $files;
        $this->extensions = $extensions;

        $this->initializeFileSignatures();
    }

    private function initializeFileSignatures(): void
    {
        $this->fileSignatures = $this->collectFileSignatures();
    }

    private function collectFileSignatures(): array
    {
        $signatures = [];

        foreach ($this->directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            );

            foreach (new RecursiveIteratorIterator($iterator) as $file) {
                if ($this->shouldMonitorFile($file)) {
                    $signatures[$file->getPathname()] = $this->getFileSignature($file);
                }
            }
        }

        foreach ($this->files as $filePath) {
            if (file_exists($filePath)) {
                $file = new SplFileInfo($filePath);
                $signatures[$filePath] = $this->getFileSignature($file);
            }
        }

        return $signatures;
    }

    private function shouldMonitorFile(SplFileInfo $file): bool
    {
        if (!$file->isFile()) {
            return false;
        }

        $extension = strtolower($file->getExtension());

        return in_array($extension, $this->extensions, true);
    }

    private function getFileSignature(SplFileInfo $file): string
    {
        return sprintf(
            '%s-%d-%d',
            $file->getFilename(),
            $file->getSize(),
            $file->getMTime()
        );
    }

    public function hasChanges(): bool
    {
        $currentSignatures = $this->collectFileSignatures();

        // Check for new or modified files
        foreach ($currentSignatures as $path => $signature) {
            if (!isset($this->fileSignatures[$path]) ||
                $this->fileSignatures[$path] !== $signature) {
                $this->fileSignatures = $currentSignatures;
                return true;
            }
        }

        // Check for deleted files
        foreach ($this->fileSignatures as $path => $_) {
            if (!isset($currentSignatures[$path])) {
                $this->fileSignatures = $currentSignatures;
                return true;
            }
        }

        return false;
    }
}