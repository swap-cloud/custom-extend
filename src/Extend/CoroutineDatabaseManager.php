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
            // We cannot clone $this because if $this is CoroutineDatabaseManager,
            // calling $manager->connection($name) on the clone will trigger this method again (infinite recursion)
            // unless we call the parent method on the clone, but we can't force parent call on an object instance.

            // Instead of cloning the manager to get a new connection via connection(),
            // we should manually use the parent logic or access the factory to create a connection.
            // But since we are extending DatabaseManager, we can call parent::connection($name)
            // However, calling parent::connection($name) on $this might return a cached connection from $this->connections array.
            // And we want a new connection for this coroutine.

            // The issue in the original code:
            // $manager = clone $this;
            // $manager->connection($name); -> This calls CoroutineDatabaseManager::connection() again on the new object!
            // Infinite recursion -> Stack overflow -> Memory exhaustion.

            // Solution:
            // We need to bypass the Coroutine check in the recursive call or use the parent implementation directly on a new instance
            // that doesn't override connection() or we need to manually create the connection.

            // Actually, we want to leverage the underlying Laravel DatabaseManager logic to create a connection,
            // but ensuring it's a NEW instance, not shared.

            // Let's look at how DatabaseManager creates connections. It uses $this->configure() and $this->makeConnection().
            // These are protected/public methods.

            // If we use parent::connection($name), it checks $this->connections[$name].
            // If it exists, it returns it.
            // If not, it creates one and stores it in $this->connections[$name].

            // In a coroutine environment, we want to isolate connections per coroutine.
            // So we can't use $this->connections because it's shared across coroutines (since the Manager is a singleton).

            // We can use the Factory to create a connection directly if we had access to it,
            // but DatabaseManager IS the factory effectively.

            // Correct approach for Coroutine-safe DatabaseManager:
            // We need to utilize the parent's connection creation logic but NOT store it in the shared $this->connections array
            // OR we store it in a coroutine-local array.

            // But parent::connection($name) will always store in $this->connections.
            // So we need to replicate the creation logic or use a transient factory.

            // A simple hack to avoid recursion if we want to use the "clone" strategy:
            // The clone is also a CoroutineDatabaseManager. So calling ->connection() on it triggers this method.
            // We can add a flag to skip the coroutine check.

            $manager = clone $this;
            $manager->connections = [];
            // We need a way to tell the clone "just behave like a normal DatabaseManager".
            // But we can't easily change the class of an object at runtime.

            // Alternative: Call makeConnection directly if it's accessible.
            // DatabaseManager::makeConnection($name) is protected.
            // But we are inside the class extending it, so we can access protected methods of $this.
            // Wait, makeConnection is protected in DatabaseManager.

            // So:
            // $connection = $this->makeConnection($name);
            // This would create a new connection.
            // However, makeConnection reads configuration.

            // Let's try to access makeConnection via reflection or just call it if we are sure.
            // In Laravel 10/11 DatabaseManager, makeConnection($name) is protected.
            // So $this->makeConnection($name) is valid.

            // BUT: connection($name) does this:
            // 1. If isset($this->connections[$name]), return it.
            // 2. Else, $this->makeConnection($name), store it, return it.

            // So we can just call $this->makeConnection($name) to get a FRESH connection without caching it in $this->connections!
            // And then store it in Coroutine Context.

            // Let's verify if makeConnection is available.
            // Assuming standard Laravel DatabaseManager structure.

            if (method_exists($this, 'makeConnection')) {
                $connection = $this->makeConnection($name);
            } else {
                // Fallback for older Laravel versions or if structure differs significantly
                // But normally DatabaseManager has makeConnection or configure + make.
                // If not available, we might have to use the clone method but break recursion.
                // We can't easily break recursion on the clone without a flag.

                // Let's try the purge method to ensure we can create a new one? No.

                // Let's assume makeConnection exists as it's standard.
                 $connection = $this->configure(
                    $this->makeConnection($name), $name
                );
                // Wait, makeConnection usually calls configure internally or vice versa?
                // In Laravel 8/9/10:
                // connection($name) -> if !isset -> $this->connections[$name] = $this->configure($this->makeConnection($name), $type);

                // protected function makeConnection($name)
                // protected function configure($connection, $type)

                // So we need to do exactly that.
            }

             // Re-implementing the logic from DatabaseManager::connection to avoid using the shared $this->connections array
             // and avoid infinite recursion.

             // First, we need to know the proper method signature.
             // We can use reflection to call protected parent methods if needed, but we inherit them.

             // To be safe and support standard Laravel behavior:
             // We should call $this->makeConnection($name) then $this->configure($connection, $type).
             // But $type is internal in connection().

             // Let's try a simpler approach used by other Coroutine managers:
             // Temporarily disable the coroutine check? No, we need it.

             // Actually, the previous code was:
             // $manager = clone $this;
             // $manager->connections = [];
             // $manager->connection($name);

             // The clone is CoroutineDatabaseManager.
             // $manager->connection($name) calls CoroutineDatabaseManager::connection($name).
             // Inside that call:
             // It checks if in coroutine... YES.
             // It checks context... NOT SET for this clone?
             // Actually Coroutine::getContext() is global for the current coroutine.
             // So it gets the SAME context.
             // It checks if 'db_connection_'.$name is set.
             // If we are here, it means it wasn't set (or we are initializing it).
             // So it goes to "Create a fresh connection by cloning the manager".
             // Creates Clone 2.
             // Clone 2 calls connection()...
             // Infinite loop.

             // FIX:
             // We must NOT call connection() on the clone if the clone is also CoroutineDatabaseManager.
             // We should call the PARENT implementation.
             // But we cannot call `parent::connection()` on `$manager` (the clone). We can only call `parent::connection()` on `$this`.
             // But `parent::connection()` on `$this` will use `$this->connections` which is shared!

             // So we MUST use `makeConnection` and `configure` on `$this` directly, bypassing the caching mechanism of `DatabaseManager`.

             // Let's check if we can simply call `parent::connection($name)` but force it to create a new one?
             // No, `purge` would remove it from `$this->connections`, but that affects other coroutines using the shared manager if not careful?
             // Actually, `$this` is the singleton DatabaseManager. If we purge, we affect everyone.

             // So we MUST create a new connection without touching `$this->connections`.

             // Laravel's DatabaseManager::connection($name) implementation:
             /*
                $name = $name ?: $this->getDefaultConnection();
                $type = $name;
                if (isset($this->connections[$name])) {
                    return $this->connections[$name];
                }
                return $this->connections[$name] = $this->configure(
                    $this->makeConnection($name), $type
                );
             */

            // So we can do:
            $connection = $this->configure(
                $this->makeConnection($name), $name
            );

            $context[$key] = $connection;

            return $connection;
        }

        return parent::connection($name);
    }

    /**
     * Prepare the database connection instance.
     *
     * We need to expose this if it's protected in parent, or just use it if we are in the same class hierarchy.
     * DatabaseManager::configure is protected.
     * DatabaseManager::makeConnection is protected.
     * So we can access them via $this.
     */
}
