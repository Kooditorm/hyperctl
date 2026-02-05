<?php
declare(strict_types=1);

namespace kooditorm\hyperctl\Watcher;


interface FileWatcherInterface
{
    public function hasChanges(): bool;
}