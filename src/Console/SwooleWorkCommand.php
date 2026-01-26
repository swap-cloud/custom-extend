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
                            {connection? : The name of the queue connection to work}
                            {--queue= : The names of the queues to work}
                            {--concurrency=10 : The number of coroutines per process}
                            {--processes=1 : The number of worker processes}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=1 : Number of times to attempt a job before logging it failed}
                            {--memory=128 : The memory limit in megabytes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process the queue using Swoole Coroutines';

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
