<?php
declare(strict_types=1);

namespace kooditorm\hyperctl;

use Hyperf\Command\Annotation\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Command]
class StopServerCommand extends StartServerCommand
{
    public function __construct()
    {
        parent::__construct();
        $this->setName('ctl:stop');
    }

    protected function configure(): void
    {
        $this->setDescription('Stop Hyperf server');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Stopping Hyperf Server');

        $pid = $this->getPidFromFile();

        if (!$pid) {
            $this->io->warning('No running server found.');
            return 0;
        }

        if (!$this->isProcessRunning($pid)) {
            $this->io->warning(sprintf('Process with PID %d is not running.', $pid));
            $this->removePidFile();
            return 0;
        }

        $this->stopServerProcess($pid);
        $this->removePidFile();

        return $this->exitCode;
    }

    protected function removePidFile(): void
    {
        $pidFile = BASE_PATH . '/runtime/hyperf.pid';
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
    }
}