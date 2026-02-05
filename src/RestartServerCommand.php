<?php
declare(strict_types=1);

namespace HyperCtl;

use Hyperf\Command\Annotation\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[Command]
class RestartServerCommand extends StartServerCommand
{
    public function __construct()
    {
        parent::__construct();
        $this->setName('tcl:restart');
    }

    protected function configure(): void
    {
        $this->setDescription('Restart Hyperf server')
            ->addOption(
                'clear',
                'c',
                InputOption::VALUE_NONE,
                'Clear runtime container before restarting'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Restarting Hyperf Server');

        // 获取当前 PID
        $pid = $this->getPidFromFile();

        // 停止现有服务
        if ($pid && $this->isProcessRunning($pid)) {
            $this->io->text('Stopping current server...');
            $this->stopServerProcess($pid);
        }

        // 处理清理选项
        $this->clear = $input->getOption('clear');
        $this->daemonize = false; // 重启时不使用守护模式

        // 重新启动服务
        $this->io->text('Starting new server...');
        $this->runServer();

        return $this->exitCode;
    }
}