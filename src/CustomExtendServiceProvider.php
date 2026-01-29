<?php

declare(strict_types=1);

namespace SwapCloud\CustomExtend;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use SwapCloud\CustomExtend\Command\PublishDockerConfigCommand;
use Slowlyo\OwlAdmin\Controllers\AdminController;
use SwapCloud\CustomExtend\Extend\Menu;
use Slowlyo\OwlAdmin\Controllers\AdminPermissionController;

use Illuminate\Support\Arr;
use SwapCloud\CustomExtend\Console\SwooleWorkCommand;
use SwapCloud\CustomExtend\Extend\CoroutineRedisManager;
use SwapCloud\CustomExtend\Extend\CoroutineDatabaseManager;

/**
 * 中间层服务提供者
 */
class CustomExtendServiceProvider extends ServiceProvider
{
    protected array $commands = [];

    public function register(): void
    {
        // 注册 Swoole Work 命令
        $this->commands([
            SwooleWorkCommand::class,
        ]);

        // 在 Swoole 环境下替换 Redis 和 Database 管理器
        if (extension_loaded('swoole')) {
            $this->app->extend('redis', function ($service, $app) {
                $config = $app->make('config')->get('database.redis', []);
                return new CoroutineRedisManager($app, Arr::pull($config, 'client', 'phpredis'), $config);
            });

            $this->app->extend('db', function ($service, $app) {
                return new CoroutineDatabaseManager($app, $app['db.factory']);
            });
        }

        // 修复 LaravelS 下扩展状态缓存问题
        $this->app->bind(\Slowlyo\OwlAdmin\Extend\Manager::class, \SwapCloud\CustomExtend\Extend\Manager::class);
        if ($this->app->bound('admin.extend')) {
            $this->app->extend('admin.extend', function ($service, $app) {
                if ($service instanceof \SwapCloud\CustomExtend\Extend\Manager) {
                    return $service;
                }

                return \SwapCloud\CustomExtend\Extend\Manager::wrap($service, $app);
            });
        }
    }

    public function boot(): void
    {
        $this->commands(array_merge($this->commands,[
            PublishDockerConfigCommand::class
        ]));
        // 覆写权限控制器
        $this->app->bind(AdminPermissionController::class,\SwapCloud\CustomExtend\Extend\AdminPermissionController::class);
        // 覆写基础控制器（用于处理文件上传问题）
        $this->app->bind(AdminController::class,\SwapCloud\CustomExtend\Extend\AdminPermissionController::class);
        // 开发者工具问题
        $this->app->bind('admin.menu',Menu::class);
        try{
            Schema::table('admin_permissions', function (Blueprint $table) {
                if (!Schema::hasColumn('admin_permissions', 'extension')) {
                    $table->string('extension')->nullable();
                }
            });
            Schema::table('admin_dict', function (Blueprint $table) {
                if (!Schema::hasColumn('admin_dict', 'extension')) {
                    $table->string('extension')->nullable();
                }
            });
        }catch (\Throwable $throwable){

        }
    }
}
