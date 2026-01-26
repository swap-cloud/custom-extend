<?php

namespace SwapCloud\CustomExtend\Extend;

use Illuminate\Queue\QueueManager;
use Swoole\Coroutine;

class CoroutineQueueManager extends QueueManager
{
    /**
     * Resolve a queue connection instance.
     *
     * @param  string  $name
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connection($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        // Check if we are in a Swoole Coroutine environment
        if (extension_loaded('swoole') && class_exists(Coroutine::class) && Coroutine::getCid() > 0) {
            $context = Coroutine::getContext();
            $key = 'queue_connection_' . $name;

            if (isset($context[$key])) {
                return $context[$key];
            }

            // Create a fresh connection
            // We use the parent's resolve method which uses the registered connectors
            // Since we are in the same class hierarchy, we can access protected resolve() method?
            // No, resolve() is protected in QueueManager.
            
            $connection = $this->resolve($name);
            
            // Set the container on the connection
            // This is critical because some Queue implementations (like RabbitMQQueue) rely on the container
            // to resolve dependencies (e.g. creating Jobs).
            // Parent QueueManager::connection() does this, but we are bypassing it.
            if (method_exists($connection, 'setContainer')) {
                $connection->setContainer($this->app);
            }

            // Store in coroutine context
            $context[$key] = $connection;

            return $connection;
        }

        return parent::connection($name);
    }

    /**
     * Set the connectors from another QueueManager.
     * 
     * @param array $connectors
     * @return void
     */
    public function setConnectors(array $connectors)
    {
        $this->connectors = $connectors;
    }
}
