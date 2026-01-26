# SwapCloud Custom Extend

中继 owl-admin、增加自定义组件的扩展包。

## 安装

```bash
composer require swap-cloud/custom-extend
```

## 快速开始

为了方便初学者避免遇到 PHP 扩展以及配置问题，推荐使用 docker-compose 作为环境编排。

1. **发布配置**

```bash
php artisan publish:docker
```

2. **启动环境**

```bash
docker-compose up -d
```

> 提示：
> - 进入容器终端: `docker-compose exec api bash`
> - 修改端口: 编辑 `docker-compose.yml` 中 `nginx` 服务的 `ports` 配置。

## 功能文档

本扩展提供了多种增强功能，详细使用说明请参考以下文档：

- **[Swoole 协程队列 (SwooleWork)](docs/swoole-work.md)**: 利用 Swoole 协程实现高并发队列处理，支持独立的协程数据库连接管理。
- **服务提供者扩展**: 解决字典自动加载及 Migration 问题。
- **API 路由与响应**: 快速定义 API 路由及统一 JSON 响应。
- **公共函数库**: 提供常用的辅助函数。
- **LaravelSwoole 支持**: 集成 LaravelSwoole 服务。

## 核心功能说明

### 1. 基础服务提供者

继承 `SwapCloud\CustomExtend\Extend\SwapCloudServiceProvider` 可自动处理字典加载和权限注册。

示例代码：
```php
class DemoServiceProvider extends SwapCloudServiceProvider
{
    // 定义菜单
    protected $menu = [...];
    // 定义字典
    protected $dict = [...];
    // 定义权限
    protected $permission = [...];
}
```

### 2. API 开发支持

- **路由定义**: 在 `src/Http/api_routes.php` 中定义 API 路由。
- **统一响应**: 使用 `SwapCloud\CustomExtend\Traits\ApiResponseTrait` 实现标准 JSON 返回。

```php
use ApiResponseTrait;
return $this->success('操作成功', ['data' => []]);
```

### 3. 公共函数

- `admin_user_role_check($role)`: 检查当前管理员是否属于指定角色。

### 4. Swoole 支持

本扩展集成了 Swoole 相关功能，包括 LaravelSwoole 的配置发布和自定义的协程队列工作进程。

#### 初始化 LaravelSwoole
```bash
php artisan laravels publish
```

#### 启动 Swoole 队列
```bash
php artisan queue:swoole-work
```
> 更多关于 Swoole 队列的详细配置和高级用法，请参阅 [SwooleWork 文档](docs/swoole-work.md)。
