<?php
declare(strict_types=1);

namespace kooditorm\hyperctl;

use kooditorm\hyperctl\Watcher\FileWatcherInterface;
use kooditorm\hyperctl\Watcher\InotifyWatcher;
use kooditorm\hyperctl\Watcher\SimpleFileWatcher;
use ErrorException;
use FilesystemIterator;
use Hyperf\Command\Annotation\Command;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Server\ServerFactory;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Swoole\Process;
use Swoole\Runtime;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function Hyperf\Support\swoole_hook_flags;

#[Command]
class StartServerCommand extends BaseCommand
{
    private const DEFAULT_INTERVAL = 3;
    private const MAX_INTERVAL = 15;
    private const MIN_INTERVAL = 1;
    private const STOP_TIMEOUT = 15;

    private const MONITOR_DIRS = [
        BASE_PATH . '/app',
        BASE_PATH . '/config',
    ];

    private const MONITOR_FILES = [
        BASE_PATH . '/.env',
    ];

    private const MONITOR_EXTENSIONS = ['env', 'php'];

    #[Inject]
    protected ContainerInterface $container;

    protected SymfonyStyle $io;
    protected int $interval;
    protected bool $clear;
    protected bool $daemonize;
    protected string $php;
    protected ?int $currentPid = null;

    public function __construct()
    {
        parent::__construct('ctl:start');
    }

    protected function configure(): void
    {
        $this->setDescription('Start Hyperf servers')
            ->addOption(
                'daemonize',
                'd',
                InputOption::VALUE_NONE,
                'Run swoole server in daemon mode'
            )
            ->addOption(
                'clear',
                'c',
                InputOption::VALUE_NONE,
                'Clear runtime container before starting'
            )
            ->addOption(
                'watch',
                'w',
                InputOption::VALUE_NONE,
                'Watch file changes and restart server automatically'
            )
            ->addOption(
                'interval',
                't',
                InputOption::VALUE_REQUIRED,
                sprintf(
                    'Interval time for file watching (%d-%d seconds)',
                    self::MIN_INTERVAL,
                    self::MAX_INTERVAL
                ),
                (string)self::DEFAULT_INTERVAL
            )
            ->addOption(
                'php',
                'p',
                InputOption::VALUE_REQUIRED,
                'PHP executable path'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ContainerExceptionInterface
     * @throws ErrorException
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->checkEnvironment();
        $this->stopExistingServer();

        $this->initializeOptions($input);

        if ($input->getOption('watch')) {
            return $this->runInWatchMode();
        }

        return $this->runServer();
    }

    protected function checkEnvironment(): void
    {
        $useShortname = ini_get('swoole.use_shortname') ?? '';
        $useShortname = strtolower(trim($useShortname));

        if (!in_array($useShortname, ['', 'off', 'false'], true)) {
            $this->io->error([
                'Swoole short name must be disabled before starting server.',
                'Please set "swoole.use_shortname = off" in your php.ini.'
            ]);
            exit(1);
        }
    }

    protected function initializeOptions(InputInterface $input): void
    {
        $this->clear = $input->getOption('clear');
        $this->daemonize = $input->getOption('daemonize');

        $interval = (int)$input->getOption('interval');
        $this->interval = $this->normalizeInterval($interval);

        $this->php = $this->resolvePhpPath($input->getOption('php'));
    }

    protected function normalizeInterval(int $interval): int
    {
        return max(
            self::MIN_INTERVAL,
            min($interval, self::MAX_INTERVAL)
        );
    }

    protected function resolvePhpPath(?string $phpPath): string
    {
        if (!empty($phpPath) && file_exists($phpPath)) {
            return $phpPath;
        }

        $executable = exec('which php') ?: 'php';

        if (!file_exists($executable)) {
            throw new RuntimeException(
                sprintf('PHP executable not found: %s', $executable)
            );
        }

        return $executable;
    }

    protected function runInWatchMode(): int
    {
        $this->io->title('Starting Hyperf Server in Watch Mode');
        $this->io->note(sprintf('Watching interval: %d seconds', $this->interval));

        while (true) {
            try {
                $this->currentPid = $this->startServerProcess();
                $this->io->success(sprintf('Server started with PID: %d', $this->currentPid));

                $this->watchForChanges();

                $this->io->note('File changes detected, restarting server...');
                $this->stopServerProcess($this->currentPid);

                // Give some time for resources to be released
                sleep(1);

            } catch (Throwable $e) {
                $this->io->error(sprintf('Error: %s', $e->getMessage()));

                if ($this->currentPid) {
                    $this->stopServerProcess($this->currentPid);
                }

                $this->io->note('Restarting after error in 3 seconds...');
                sleep(3);
            }
        }

        return $this->exitCode;
    }

    /**
     * @return int
     * @throws ContainerExceptionInterface
     * @throws ErrorException
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    protected function runServer(): int
    {
        $this->io->title('Starting Hyperf Server');

        if ($this->clear) {
            $this->clearRuntimeContainer();
        }

        $this->startServer();

        return 0;
    }

    protected function clearRuntimeContainer(): void
    {
        $runtimePath = BASE_PATH . '/runtime/container';

        if (is_dir($runtimePath)) {
            $this->io->text('Clearing runtime container...');

            $iterator = new RecursiveDirectoryIterator(
                $runtimePath,
                FilesystemIterator::SKIP_DOTS
            );
            $files = new RecursiveIteratorIterator(
                $iterator,
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }

            rmdir($runtimePath);
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     * @throws ErrorException
     */
    protected function startServer(): void
    {
        try {
            $serverFactory = $this->container->get(ServerFactory::class)
                ->setEventDispatcher($this->container->get(EventDispatcherInterface::class))
                ->setLogger($this->container->get(StdoutLoggerInterface::class));

            $serverConfig = $this->container->get(ConfigInterface::class)->get('server', []);

            if (empty($serverConfig)) {
                throw new InvalidArgumentException('At least one server should be defined in configuration.');
            }

            if ($this->daemonize) {
                $serverConfig['settings']['daemonize'] = true;
                $this->io->success('Swoole server started in daemon mode.');
            }

            Runtime::enableCoroutine(swoole_hook_flags());

            $serverFactory->configure($serverConfig);
            $serverFactory->start();

        } catch (Throwable $e) {
            $this->io->error(sprintf('Failed to start server: %s', $e->getMessage()));
            throw $e;
        }
    }

    protected function stopExistingServer(): void
    {
        $this->stopServerProcess($this->getPidFromFile());
    }

    protected function getPidFromFile(): ?int
    {
        $pidFile = BASE_PATH . '/runtime/hyperf.pid';

        if (!file_exists($pidFile)) {
            return null;
        }

        $pid = (int)file_get_contents($pidFile);

        return $pid > 0 ? $pid : null;
    }

    protected function isProcessRunning(int $pid): bool
    {
        return Process::kill($pid, 0);
    }

    protected function startServerProcess(): int
    {
        if ($this->clear) {
            $this->clearRuntimeContainer();
        }

        $process = new Process(function (Process $process) {
            $args = [BASE_PATH . '/bin/hyperf.php', 'start'];

            if ($this->daemonize) {
                $args[] = '-d';
            }

            $process->exec($this->php, $args);
        }, false, 0, true);

        $pid = $process->start();

        if ($pid === false) {
            throw new RuntimeException('Failed to start server process');
        }

        return $pid;
    }

    protected function stopServerProcess(?int $pid): void
    {
        if (!$pid || !$this->isProcessRunning($pid)) {
            return;
        }

        $this->io->text(sprintf('Stopping server process with PID: %d', $pid));

        Process::kill($pid, SIGTERM);

        if (!$this->waitForProcessExit($pid, self::STOP_TIMEOUT)) {
            $this->io->warning('Process did not exit gracefully, forcing kill...');
            Process::kill($pid, SIGKILL);
            sleep(1);
        }

        $this->io->success('Server process stopped');
    }

    protected function waitForProcessExit(int $pid, int $timeout = 10): bool
    {
        $startTime = time();

        while (time() - $startTime < $timeout) {
            if (!$this->isProcessRunning($pid)) {
                return true;
            }
            sleep(1);
        }

        return false;
    }

    /**
     * @throws Throwable
     */
    protected function watchForChanges(): void
    {
        $watcher = $this->createFileWatcher();

        while (true) {
            try {
                if ($watcher->hasChanges()) {
                    return;
                }

                sleep($this->interval);

                // Check if process is still alive
                if ($this->currentPid && !$this->isProcessRunning($this->currentPid)) {
                    throw new RuntimeException('Server process died unexpectedly');
                }

            } catch (Throwable $e) {
                $this->io->error(sprintf('Watch error: %s', $e->getMessage()));
                throw $e;
            }
        }
    }

    protected function createFileWatcher(): FileWatcherInterface
    {
        if (extension_loaded('inotify')) {
            return new InotifyWatcher(
                self::MONITOR_DIRS,
                self::MONITOR_FILES,
                $this->interval
            );
        }

        return new SimpleFileWatcher(
            self::MONITOR_DIRS,
            self::MONITOR_FILES,
            self::MONITOR_EXTENSIONS,
        );
    }
}