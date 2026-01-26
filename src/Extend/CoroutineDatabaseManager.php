<?php

namespace SwapCloud\CustomExtend\Extend;

use Illuminate\Database\DatabaseManager;
use Swoole\Coroutine;

class CoroutineDatabaseManager extends DatabaseManager
{
    /**
     * Get a database connection instance.
     *
     * @param  string|null  $name
     * @return \Illuminate\Database\Connection
     */
    public function connection($name = null)
    {
        $name = $name ?: $this->getDefaultConnection();

        // Check if we are in a Swoole Coroutine environment
        if (extension_loaded('swoole') && class_exists(Coroutine::class) && Coroutine::getCid() > 0) {
            $context = Coroutine::getContext();
            $key = 'db_connection_' . $name;

            if (isset($context[$key])) {
                return $context[$key];
            }

            // Create a fresh connection by cloning the manager
            $manager = clone $this;
            
            // Clear the connections array of the clone to force a new connection
            // We use reflection or just assume we can access it if we extended (protected property)
            // DatabaseManager $connections is protected.
            $manager->connections = [];
            
            // Resolve the connection using the clone
            // calling connection() on the clone will trigger creation since connections array is empty
            $connection = $manager->connection($name);

            // Store in coroutine context
            $context[$key] = $connection;

            return $connection;
        }

        return parent::connection($name);
    }
}
