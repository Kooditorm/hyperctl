<?php
declare(strict_types=1);

namespace HyperCtl\Watcher;


interface FileWatcherInterface
{
    public function hasChanges(): bool;
}