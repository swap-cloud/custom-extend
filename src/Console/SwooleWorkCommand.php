<?php

namespace SwapCloud\CustomExtend\Console;

use Illuminate\Console\Command;
use Illuminate\Queue\WorkerOptions;
use SwapCloud\CustomExtend\Extend\Queue\SwooleWorker;
use Swoole\Process\Pool;
use Swoole\Runtime;

use SwapCloud\CustomExtend\Extend\CoroutineQueueManager;

class SwooleWorkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:swoole-work
                            {connection? : 要处理的队列连接名称}
                            {--queue= : 要处理的队列名称}
                            {--concurrency=10 : 每个进程的协程数量}
                            {--processes=1 : 工作进程的数量}
                            {--sleep=3 : 当没有任务可用时休眠的秒数}
                            {--timeout=60 : 子进程可以运行的秒数}
                            {--tries=1 : 任务失败前尝试的次数}
                            {--memory=128 : 内存限制（兆字节）}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '使用 Swoole 协程处理队列';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $connection = $this->argument('connection') ?: config('queue.default');
        $queue = $this->option('queue') ?: config("queue.connections.{$connection}.queue", 'default');

        $concurrency = (int) $this->option('concurrency');
        $processes = (int) $this->option('processes');

        if (!extension_loaded('swoole')) {
            $this->error('Swoole extension is not installed.');
            return 1;
        }

        $this->info("Starting Swoole Queue Worker...");
        $this->info("Connection: {$connection}");
        $this->info("Queue: {$queue}");
        $this->info("Processes: {$processes}");
        $this->info("Concurrency per Process: {$concurrency}");

        $pool = new Pool($processes);

        $pool->on('WorkerStart', function ($pool, $workerId) use ($connection, $queue, $concurrency) {
            // Enable Swoole Hook for all IO operations
            Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

            // Re-seed the random number generator in the child process
            mt_srand();

            // Create options
            // We use standard constructor to support Laravel 10/11
            $options = new WorkerOptions(
                'default',          // name
                0,                  // backoff
                (int) $this->option('memory'), // memory
                (int) $this->option('timeout'), // timeout
                (int) $this->option('sleep'),   // sleep
                (int) $this->option('tries'),   // maxTries
                false,              // force
                false               // stopWhenEmpty
            );

            // Run inside Coroutine container
            \Swoole\Coroutine\run(function () use ($connection, $queue, $options, $concurrency) {
                try {
                    // Create CoroutineQueueManager
                    $originalManager = app('queue');
                    $coroutineManager = new CoroutineQueueManager(app());

                    // Copy connectors from original manager to our custom one
                    // We need reflection to access protected $connectors property
                    $reflection = new \ReflectionClass($originalManager);
                    $property = $reflection->getProperty('connectors');
                    $property->setAccessible(true);
                    $connectors = $property->getValue($originalManager);
                    $coroutineManager->setConnectors($connectors);

                    $worker = new SwooleWorker(
                        $coroutineManager,
                        app('events'),
                        app(\Illuminate\Contracts\Debug\ExceptionHandler::class),
                        function () {
                            return app()->isDownForMaintenance();
                        }
                    );
                    $worker->setName('default');

                    $this->line("Worker process started (PID: " . getmypid() . ")");

                    // Start the coroutine loop
                    $worker->swooleLoop($connection, $queue, $options, $concurrency);

                } catch (\Throwable $e) {
                    $this->error("Worker failed: " . $e->getMessage());
                }
            });
        });

        $pool->start();

        return 0;
    }
}
