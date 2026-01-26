<?php

namespace SwapCloud\CustomExtend\Extend;

use Illuminate\Redis\RedisManager;
use Swoole\Coroutine;

class CoroutineRedisManager extends RedisManager
{
    /**
     * Get a Redis connection instance.
     *
     * @param  string|null  $name
     * @return \Illuminate\Redis\Connections\Connection
     */
    public function connection($name = null)
    {
        $name = $name ?: 'default';

        // Check if we are in a Swoole Coroutine environment
        if (extension_loaded('swoole') && class_exists(Coroutine::class) && Coroutine::getCid() > 0) {
            $context = Coroutine::getContext();
            $key = 'redis_connection_' . $name;

            if (isset($context[$key])) {
                return $context[$key];
            }

            // Create a fresh connection by cloning the manager
            // This avoids polluting the global singleton's connection cache
            $manager = clone $this;
            
            // Clear the connections array of the clone to force a new connection
            $manager->connections = [];

            // Disconnect current connection if it was accidentally copied or shared
            if (isset($this->connections[$name])) {
                // We don't want to close the parent's connection, just ensure the clone doesn't use it
                // But since we cleared $manager->connections = [], it shouldn't be there.
            }
            
            // Resolve the connection using the clone
            // Since we are in the same class, we can access protected methods/properties of the instance
            // But 'resolve' might not be enough if it uses the same $this->app['config'] which is fine,
            // but we need to ensure the underlying connector creates a NEW resource.
            
            // For PhpRedis, the connector calls $client->connect().
            // If we use persistent connections, they might be shared?
            // Ensure we are NOT using persistent connections or we handle them correctly.
            
            $connection = $manager->resolve($name);
            
            // Force re-connect if needed?
            // The resolved connection is a new object (PhpRedisConnection).
            // It holds a new Redis client instance?
            // RedisConnector creates a new Redis instance.
            
            // Store in coroutine context
            $context[$key] = $connection;

            return $connection;
        }

        // echo "[CoroutineRedisManager] Returning GLOBAL connection (Not in coroutine)\n";
        return parent::connection($name);
    }
}
