<?php

declare(strict_types=1);

namespace SwapCloud\CustomExtend\Extend;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use SwapCloud\CustomExtend\Traits\CanImportDict;
use SwapCloud\CustomExtend\Traits\CanImportMenu;
use SwapCloud\CustomExtend\Traits\CanImportPermission;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Slowlyo\OwlAdmin\Admin;
use Slowlyo\OwlAdmin\Events\ExtensionChanged;
use Slowlyo\OwlAdmin\Extend\ServiceProvider;
use SwapCloud\CustomExtend\Command\PublishDockerConfigCommand;
use Swoole\Coroutine;
use Swoole\Runtime;

/**
 * SwapCloud 的 服务提供者
 */
class SwapCloudServiceProvider extends ServiceProvider
{
    use CanImportMenu, CanImportDict, CanImportPermission;

    protected $menu = [];
    protected $dict = [];
    protected $permission = [];
    protected $commands = [];

    /**
     * 获取API路由地址
     * @return string|null
     * @throws \Exception
     */
    public function getApiRoutes(): ?string
    {
        $path = $this->path('src/Http/api_routes.php');

        return is_file($path) ? $path : null;
    }

    /**
     * 注册API路由.
     *
     * @param $callback
     */
    public function registerApiRoutes($callback): void
    {
        Route::group(
            array_filter([
                'domain' => Admin::config('admin.route.domain'),
                'prefix' => Admin::config('admin.route.prefix'),
                'middleware' => Admin::config('admin.route.middleware'),
            ]),
            $callback
        );
    }

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function initRoutes(): void
    {
        if ($this->disabled()) {
            return;
        }

        if ($routes = $this->getRoutes()) {
            $this->registerRoutes($routes);
        }
        if ($routes = $this->getApiRoutes()) {
            include_once($this->getApiRoutes());
        }
    }
    public function boot()
    {
        // 判断是否在Swoole 环境
        if (extension_loaded('swoole')) {
            // 动态注册 LaravelS 服务提供者
            $this->registerLaravelSProvider();
        }
        // 在协程环境下启用 Swoole Hook，自动 hook 所有 IO 操作
        $this->enableSwooleHookInCoroutine();
        // 注册命令行
        $this->commands(array_merge($this->commands, [
            PublishDockerConfigCommand::class
        ]));
        parent::boot();
    }
    /**
     * 动态注册 LaravelS 服务提供者
     */
    private function registerLaravelSProvider(): void
    {
        // 检查 LaravelS 服务提供者是否存在
        if (class_exists('\\Hhxsv5\\LaravelS\\Illuminate\\LaravelSServiceProvider')) {
            // 动态注册 LaravelS 服务提供者
            $this->app->register('\\Hhxsv5\\LaravelS\\Illuminate\\LaravelSServiceProvider');
            // Log::info('LaravelS 服务提供者已动态注册');
        } else {
            // Log::debug('LaravelS 服务提供者类不存在，跳过注册');
        }
    }
    /**
     * 监听扩展注册事件
     * @return void
     */
    public function register()
    {
        /**
         * 监听启用禁用 事件
         */
        Event::listen(ExtensionChanged::class, function (ExtensionChanged $event) {
            if ($event->name == '*' && $event->type = 'gen-permission') {
                // 生成权限
                if (method_exists($this, 'refreshPermission')) {
                    try {
                        $this->refreshPermission();
                    } catch (\Throwable $throwable) {
                    }
                }
            }
            if ($event->name === $this->getName() && $event->type == 'enable') {
                // 安装菜单
                if (method_exists($this, 'refreshMenu')) {
                    $this->refreshMenu();
                }
                // 安装字典
                if (method_exists($this, 'refreshDict')) {
                    $this->refreshDict();
                }
                $this->runMigrations();
                // 生成权限
                if (method_exists($this, 'refreshPermission')) {
                    $this->refreshPermission();
                }
            } else if ($event->name === $this->getName() && $event->type == 'disable') {
                // 删除菜单
                if (method_exists($this, 'flushMenu')) {
                    $this->flushMenu();
                }
                // 删除字典
                if (method_exists($this, 'flushDict')) {
                    $this->flushDict();
                }
                // 删除权限
                if (method_exists($this, 'flushPermission')) {
                    $this->flushPermission();
                }
            } else if ($event->name === $this->getName() && $event->type == 'install') {
                // 安装菜单
                if (method_exists($this, 'refreshMenu')) {
                    $this->refreshMenu();
                }
                // 安装字典
                if (method_exists($this, 'refreshDict')) {
                    $this->refreshDict();
                }
                $this->runMigrations();
            } else if ($event->name === $this->getName() && $event->type == 'uninstall') {
                // 删除菜单
                if (method_exists($this, 'flushMenu')) {
                    $this->flushMenu();
                }
                // 删除字典
                if (method_exists($this, 'flushDict')) {
                    $this->flushDict();
                }
            }
        });
    }

    /**
     * 在协程环境下启用 Swoole Hook
     * 自动 hook 所有 IO 操作，避免阻塞
     */
    private function enableSwooleHookInCoroutine(): void
    {
        // 检查是否安装了 Swoole 扩展
        if (!extension_loaded('swoole')) {
            // Log::debug('Swoole 扩展未安装，跳过协程 Hook 设置');
            return;
        }

        // 检查是否在协程环境中
        if (!class_exists('\\Swoole\\Coroutine') || !method_exists('\\Swoole\\Coroutine', 'getCid')) {
            // Log::debug('Swoole Coroutine 类不存在，跳过协程 Hook 设置');
            return;
        }

        try {
            // 检查当前是否在协程环境中
            $cid = Coroutine::getCid();
            if ($cid > 0) {
                // Log::info('检测到协程环境，启用 Swoole Runtime Hook', [
                //     'coroutine_id' => $cid,
                //     'hook_flags' => $this->getSwooleHookFlags()
                // ]);

                // 启用 Swoole Runtime Hook，hook 所有 IO 操作
                Runtime::enableCoroutine($this->getSwooleHookFlags());

                // Log::info('Swoole Runtime Hook 已启用', [
                //     'coroutine_id' => $cid,
                //     'hook_enabled' => true
                // ]);
            } else {
                // Log::debug('当前不在协程环境中，跳过 Hook 设置', [
                //     'coroutine_id' => $cid
                // ]);
            }
        } catch (\Throwable $e) {
            Log::warning('启用 Swoole Hook 时发生错误', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * 获取 Swoole Hook 标志
     * 定义需要 hook 的 IO 操作类型
     *
     * @return int
     */
    private function getSwooleHookFlags(): int
    {
        $flags = 0;

        // 检查并添加各种 Hook 标志
        if (defined('SWOOLE_HOOK_TCP')) {
            $flags |= SWOOLE_HOOK_TCP;  // Hook TCP 连接
        }

        if (defined('SWOOLE_HOOK_UDP')) {
            $flags |= SWOOLE_HOOK_UDP;  // Hook UDP 连接
        }

        if (defined('SWOOLE_HOOK_UNIX')) {
            $flags |= SWOOLE_HOOK_UNIX;  // Hook Unix Socket
        }

        if (defined('SWOOLE_HOOK_UDG')) {
            $flags |= SWOOLE_HOOK_UDG;  // Hook Unix Datagram
        }

        if (defined('SWOOLE_HOOK_SSL')) {
            $flags |= SWOOLE_HOOK_SSL;  // Hook SSL/TLS
        }

        if (defined('SWOOLE_HOOK_TLS')) {
            $flags |= SWOOLE_HOOK_TLS;  // Hook TLS
        }

        if (defined('SWOOLE_HOOK_STREAM_FUNCTION')) {
            $flags |= SWOOLE_HOOK_STREAM_FUNCTION;  // Hook stream 函数
        }

        if (defined('SWOOLE_HOOK_FILE')) {
            $flags |= SWOOLE_HOOK_FILE;  // Hook 文件操作
        }

        if (defined('SWOOLE_HOOK_SLEEP')) {
            $flags |= SWOOLE_HOOK_SLEEP;  // Hook sleep 函数
        }

        if (defined('SWOOLE_HOOK_PROC')) {
            $flags |= SWOOLE_HOOK_PROC;  // Hook 进程操作
        }

        if (defined('SWOOLE_HOOK_CURL')) {
            $flags |= SWOOLE_HOOK_CURL;  // Hook cURL
        }

        if (defined('SWOOLE_HOOK_NATIVE_CURL')) {
            $flags |= SWOOLE_HOOK_NATIVE_CURL;  // Hook 原生 cURL
        }

        if (defined('SWOOLE_HOOK_BLOCKING_FUNCTION')) {
            $flags |= SWOOLE_HOOK_BLOCKING_FUNCTION;  // Hook 阻塞函数
        }

        if (defined('SWOOLE_HOOK_SOCKETS')) {
            $flags |= SWOOLE_HOOK_SOCKETS;  // Hook sockets 扩展
        }

        if (defined('SWOOLE_HOOK_STDIO')) {
            $flags |= SWOOLE_HOOK_STDIO;  // Hook 标准输入输出
        }

        if (defined('SWOOLE_HOOK_PDO_PGSQL')) {
            $flags |= SWOOLE_HOOK_PDO_PGSQL;  // Hook PDO PostgreSQL
        }

        if (defined('SWOOLE_HOOK_PDO_ODBC')) {
            $flags |= SWOOLE_HOOK_PDO_ODBC;  // Hook PDO ODBC
        }

        if (defined('SWOOLE_HOOK_PDO_ORACLE')) {
            $flags |= SWOOLE_HOOK_PDO_ORACLE;  // Hook PDO Oracle
        }

        if (defined('SWOOLE_HOOK_PDO_SQLITE')) {
            $flags |= SWOOLE_HOOK_PDO_SQLITE;  // Hook PDO SQLite
        }

        // 如果支持 SWOOLE_HOOK_ALL，使用它来 hook 所有支持的操作
        if (defined('SWOOLE_HOOK_ALL')) {
            $flags = SWOOLE_HOOK_ALL;
        }

        // 如果没有找到任何标志，使用默认值
        if ($flags === 0) {
            Log::warning('未找到 Swoole Hook 标志，使用默认值');
            // 使用一些基本的标志作为后备
            $flags = SWOOLE_HOOK_TCP | SWOOLE_HOOK_UDP | SWOOLE_HOOK_FILE | SWOOLE_HOOK_SLEEP;
        }

        return $flags;
    }
}
