<?php

declare(strict_types=1);

namespace SwapCloud\CustomExtend\Extend;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use SwapCloud\CustomExtend\Traits\CanImportDict;
use SwapCloud\CustomExtend\Traits\CanImportMenu;
use SwapCloud\CustomExtend\Traits\CanImportPermission;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Slowlyo\OwlAdmin\Admin;
use Slowlyo\OwlAdmin\Events\ExtensionChanged;
use Slowlyo\OwlAdmin\Extend\ServiceProvider;

/**
 * SwapCloud 的 服务提供者
 */
class SwapCloudServiceProvider extends ServiceProvider
{
    use CanImportMenu, CanImportDict,CanImportPermission;

    protected $menu = [];
    protected $dict = [];
    protected $permission = [];

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
        Route::group(array_filter([
            'domain' => Admin::config('admin.route.domain'),
            'prefix' => Admin::config('admin.route.prefix'),
            'middleware' => Admin::config('admin.route.middleware'),
        ]),
            $callback);
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
            Route::group([
                'middleware'=>[
                    SetLocaleFromHeader::class
                ]
            ],function () use($routes){
                include_once($routes);
            });

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
                    try{
                        $this->refreshPermission();
                    }catch (\Throwable $throwable){

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
}
