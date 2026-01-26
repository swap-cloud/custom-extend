# Swoole 协程队列工作进程 (SwooleWork)

`SwooleWork` 利用 Swoole 协程的高性能特性来处理 Laravel 队列。与传统的队列工作进程相比，它能显著提高并发处理能力和资源利用率。

## 功能特性

- **高并发**: 使用轻量级协程替代繁重的进程或线程，单进程内可同时处理多个任务。
- **资源高效**: 相比传统多进程模式，协程消耗的内存和 CPU 更少。
- **非阻塞 I/O**: Swoole 的异步 I/O 操作防止了数据库、Redis 或外部 API 调用时的阻塞。
- **独立上下文**: 每个协程任务拥有独立的数据库和队列连接上下文，避免连接污染。

## 基础用法

启动处理默认队列的协程 Worker：

```bash
php artisan queue:swoole-work
```

## 高级选项与配置

该命令支持多种参数来定制 Worker 的行为：

- `{connection?}`: 指定使用的队列连接名称（默认为配置的默认连接）
- `{--queue=}`: 指定处理的队列名称（多个队列用逗号分隔）
- `{--concurrency=10}`: 每个进程内的并发协程数（默认：10）
- `{--processes=1}`: 启动的工作进程数量（默认：1）
- `{--sleep=3}`: 当没有任务时休眠的秒数（默认：3）
- `{--timeout=60}`: 子进程运行的最大秒数（默认：60）
- `{--tries=1}`: 任务失败前的最大尝试次数（默认：1）
- `{--memory=128}`: 内存限制，单位 MB（默认：128）

## 使用示例

### 1. 指定连接和队列

处理 `redis` 连接下的 `emails` 和 `notifications` 队列：

```bash
php artisan queue:swoole-work redis --queue=emails,notifications
```

### 2. 高并发处理

启动 4 个进程，每个进程处理 25 个并发协程（总计 100 并发）：

```bash
php artisan queue:swoole-work --processes=4 --concurrency=25
```

### 3. 自定义重试和内存限制

```bash
php artisan queue:swoole-work --tries=3 --memory=512
```

## 技术原理

SwooleWork 通过 `SwapCloud\CustomExtend\Extend\CoroutineQueueManager` 和 `CoroutineDatabaseManager` 实现了连接的协程隔离。

- **CoroutineQueueManager**: 确保在协程环境中获取队列连接时，每个协程拥有独立的连接实例。
- **CoroutineDatabaseManager**: 确保数据库操作在协程中安全执行，自动处理连接的克隆和隔离。
- **CoroutineRedisManager**: 确保 Redis 操作在协程中安全执行，防止多协程复用同一 socket 导致的数据错乱。

### 模型支持 (BaseModel)

为了在协程环境中正确处理 Eloquent 模型连接，建议您的模型继承自 `SwapCloud\CustomExtend\Extend\BaseModel`。该基类重写了连接解析器逻辑，确保每个协程使用正确的数据库连接上下文。

```php
use SwapCloud\CustomExtend\Extend\BaseModel;

class User extends BaseModel
{
    // ...
}
```

当 `queue:swoole-work` 启动时，它会创建一个进程池。在每个工作进程中，启用 Swoole 的一键协程化 (`SWOOLE_HOOK_ALL`)，使得原本阻塞的 PHP I/O 函数（如 PDO, Redis, file_get_contents 等）变为非阻塞模式。
