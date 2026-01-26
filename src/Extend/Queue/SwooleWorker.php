<?php

namespace SwapCloud\CustomExtend\Extend\Queue;

use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Swoole\Coroutine;
use Throwable;

class SwooleWorker extends Worker
{
    /**
     * Run the worker in a Swoole coroutine loop.
     *
     * @param  string  $connectionName
     * @param  string  $queue
     * @param  \Illuminate\Queue\WorkerOptions  $options
     * @param  int  $concurrency
     * @return void
     */
    public function swooleLoop($connectionName, $queue, WorkerOptions $options, $concurrency = 10)
    {
        $this->listenForSignals();

        // Create a channel to control concurrency or just launch N coroutines
        for ($i = 0; $i < $concurrency; $i++) {
            Coroutine::create(function () use ($connectionName, $queue, $options, $i) {
                // Ensure each coroutine has its own worker instance or at least manages its dependencies correctly
                // Actually, the Worker instance ($this) is shared across coroutines in the current implementation.
                // The Worker->runNextJob calls $this->manager->connection($connectionName).
                // $this->manager is the QueueManager instance we passed to the constructor.
                // In SwooleWorkCommand, we cloned QueueManager for the PROCESS, but here we are in a loop spawning COROUTINES.
                // If $this->manager is shared across coroutines, and QueueManager caches connections in $connections array,
                // then all coroutines will get the SAME RedisQueue instance from $this->manager->connection().
                // And that RedisQueue instance holds ONE Redis connection.
                
                // We need to make sure that INSIDE the coroutine, when we ask for a connection, we get a NEW one or a context-isolated one.
                
                // Problem: $this->manager is shared. $this->manager->connection('redis') returns a singleton-ish RedisQueue instance for 'redis' connection name.
                // Even if CoroutineRedisManager returns different Redis instances, the RedisQueue instance itself might be cached by QueueManager.
                
                // To fix this properly, we need to bypass the QueueManager's caching mechanism OR ensure QueueManager returns a fresh RedisQueue 
                // that uses the current coroutine's Redis connection.
                
                // Since we cannot easily modify QueueManager's internal caching behavior from here without dirty hacks,
                // we should manually resolve the queue connection inside the coroutine loop if possible, 
                // OR we rely on CoroutineRedisManager's ability to return the correct underlying redis instance 
                // even if the RedisQueue object is shared.
                
                // Wait... RedisQueue holds the 'redis' service (RedisManager/Factory).
                // RedisQueue->pop() calls $this->redis->connection()->pop().
                // $this->redis IS our CoroutineRedisManager.
                // So calling connection() on it SHOULD return the context-aware connection.
                
                // HOWEVER, QueueManager resolves the driver (RedisQueue) only ONCE and caches it.
                // When creating RedisQueue, it passes the redis manager instance:
                // new RedisQueue($this->app['redis'], ...)
                
                // So the shared RedisQueue instance holds the shared CoroutineRedisManager instance.
                // When any coroutine calls $redisQueue->pop(), it calls $coroutineRedisManager->connection()->eval().
                // This LOOKS correct.
                
                // But the error says: Socket#28 has already been bound to another coroutine#20
                // This means the underlying PhpredisConnection is being shared.
                
                // Let's force a context switch or sleep to ensure setup is clean? No.
                
                while (true) {
                    if ($this->shouldQuit) {
                        break;
                    }

                    try {
                        // Run the next job
                        // This will call getNextJob(), which calls connection->pop()
                        // If Redis driver is used with 'block_for', it will block (yield) here.
                        $this->runNextJob($connectionName, $queue, $options);
                        
                    } catch (Throwable $e) {
                        $this->exceptions->report($e);
                        $this->sleep(1);
                    }
                    
                    // Small yield to prevent CPU hogging if runNextJob returns immediately (e.g. empty queue without blocking)
                    Coroutine::sleep(0.01);
                }
            });
        }
    }

    /**
     * Sleep the script for a given number of seconds.
     * Overridden to use Coroutine::sleep to avoid blocking the process.
     *
     * @param  int|float  $seconds
     * @return void
     */
    public function sleep($seconds)
    {
        if ($seconds > 0) {
            Coroutine::sleep((float) $seconds);
        }
    }

    /**
     * Stop listening and bail out of the script.
     *
     * @param  int  $status
     * @param  \Illuminate\Queue\WorkerOptions|null  $options
     * @return void
     */
    public function stop($status = 0, $options = null)
    {
        $this->shouldQuit = true;
        // We don't exit here, we let the loops finish
    }
}
