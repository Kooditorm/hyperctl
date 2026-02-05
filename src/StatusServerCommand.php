<?php
declare(strict_types=1);

namespace HyperCtl;

use Hyperf\Command\Annotation\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Command]
class StatusServerCommand extends StartServerCommand
{
    public function __construct()
    {
        parent::__construct();
        $this->setName('tcl:status');
    }

    protected function configure(): void
    {
        $this->setDescription('Check Hyperf server status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Hyperf Server Status');

        $pid = $this->getPidFromFile();

        if (!$pid) {
            $this->io->error('Server is not running.');
            return $this->exitCode;
        }

        if ($this->isProcessRunning($pid)) {
            $this->io->success(sprintf('Server is running with PID: %d', $pid));

            // 可以添加更多状态信息，如内存使用、运行时间等
            if (function_exists('posix_getpwuid')) {
                $processInfo = posix_getpwuid(@fileowner("/proc/$pid"));
                if ($processInfo) {
                    $this->io->table(
                        ['Property', 'Value'],
                        [
                            ['PID', $pid],
                            ['User', $processInfo['name']],
                            ['Command', trim(@file_get_contents("/proc/$pid/cmdline"))],
                        ]
                    );
                }
            }

            return $this->exitCode;
        }

        $this->io->error(sprintf('PID file exists but process %d is not running.', $pid));
        return $this->exitCode;
    }
}