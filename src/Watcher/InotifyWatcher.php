<?php
declare(strict_types=1);

namespace kooditorm\hyperctl\Watcher;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class InotifyWatcher implements FileWatcherInterface
{
    private array $watchDescriptors = [];
    private $inotify;
    private int $interval;

    public function __construct(
        array $directories,
        array $files,
        int   $interval
    )
    {
        $this->interval = $interval;
        $this->initializeInotify($directories, $files);
    }

    private function initializeInotify(array $directories, array $files): void
    {
        $this->inotify = inotify_init();
        stream_set_blocking($this->inotify, false);

        $allPaths = $this->getAllWatchPaths($directories, $files);

        foreach ($allPaths as $path) {
            $wd = inotify_add_watch(
                $this->inotify,
                $path,
                IN_CLOSE_WRITE | IN_CREATE | IN_DELETE | IN_MODIFY | IN_MOVE
            );

            if ($wd !== false) {
                $this->watchDescriptors[$wd] = $path;
            }
        }
    }

    private function getAllWatchPaths(array $directories, array $files): array
    {
        $paths = [];

        foreach ($directories as $dir) {
            $paths[] = $dir;

            $iterator = new RecursiveDirectoryIterator(
                $dir,
                FilesystemIterator::SKIP_DOTS
            );

            foreach (new RecursiveIteratorIterator($iterator) as $item) {
                if ($item->isDir()) {
                    $paths[] = $item->getPathname();
                }
            }
        }

        return array_merge($paths, $files);
    }

    public function hasChanges(): bool
    {
        sleep($this->interval);

        $events = inotify_read($this->inotify);

        return !empty($events);
    }

    public function __destruct()
    {
        if (is_resource($this->inotify)) {
            foreach (array_keys($this->watchDescriptors) as $wd) {
                @inotify_rm_watch($this->inotify, $wd);
            }
            fclose($this->inotify);
        }
    }
}