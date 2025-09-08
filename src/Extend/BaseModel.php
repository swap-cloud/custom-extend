<?php

declare(strict_types=1);

namespace SwapCloud\CustomExtend\Extend;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\ConnectionResolverInterface;
use Swoole\Coroutine;

/**
 * 基础模型类
 * 解决协程环境下Model resolver static问题
 * 确保每个协程使用独立的数据库连接解析器
 */
class BaseModel extends Model
{
    /**
     * 协程上下文中存储连接解析器的键
     */
    const COROUTINE_RESOLVER_KEY = 'model_connection_resolver';

    /**
     * 属性类型转换
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 为数组/JSON序列化准备日期格式
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 获取数据库连接解析器
     * 在协程环境下，每个协程使用独立的解析器
     *
     * @return ConnectionResolverInterface
     */
    public static function getConnectionResolver()
    {
        // 检查是否安装了 Swoole 扩展
        if (!extension_loaded('swoole') || !class_exists('\\Swoole\\Coroutine')) {
            return parent::getConnectionResolver();
        }
        
        // 检查是否在协程环境中
        if (Coroutine::getCid() > 0) {
            $context = Coroutine::getContext();

            // 如果协程上下文中已有解析器，直接返回
            if (isset($context[self::COROUTINE_RESOLVER_KEY])) {
                return $context[self::COROUTINE_RESOLVER_KEY];
            }

            // 如果没有，使用全局解析器并存储到协程上下文
            $resolver = parent::getConnectionResolver();
            $context[self::COROUTINE_RESOLVER_KEY] = $resolver;

            return $resolver;
        }

        // 非协程环境，使用默认行为
        return parent::getConnectionResolver();
    }

    /**
     * 设置数据库连接解析器
     * 在协程环境下，设置到协程上下文中
     *
     * @param ConnectionResolverInterface $resolver
     * @return void
     */
    public static function setConnectionResolver(ConnectionResolverInterface $resolver)
    {
        // 检查是否安装了 Swoole 扩展
        if (!extension_loaded('swoole') || !class_exists('\\Swoole\\Coroutine')) {
            parent::setConnectionResolver($resolver);
            return;
        }
        
        // 检查是否在协程环境中
        if (Coroutine::getCid() > 0) {
            $context = Coroutine::getContext();
            $context[self::COROUTINE_RESOLVER_KEY] = $resolver;
        } else {
            // 非协程环境，使用默认行为
            parent::setConnectionResolver($resolver);
        }
    }

    /**
     * 清理协程上下文中的连接解析器
     * 在协程结束时调用，防止内存泄漏
     *
     * @return void
     */
    public static function clearCoroutineResolver()
    {
        // 检查是否安装了 Swoole 扩展
        if (!extension_loaded('swoole') || !class_exists('\\Swoole\\Coroutine')) {
            return;
        }
        
        if (Coroutine::getCid() > 0) {
            $context = Coroutine::getContext();
            unset($context[self::COROUTINE_RESOLVER_KEY]);
        }
    }

    /**
     * 获取数据库连接
     * 确保在协程环境下使用正确的连接解析器
     *
     * @param string|null $connection
     * @return \Illuminate\Database\Connection
     */
    public function getConnection($connection = null)
    {
        return static::resolveConnection($connection ?: $this->getConnectionName());
    }

    /**
     * 解析数据库连接
     * 使用协程安全的连接解析器
     *
     * @param string|null $connection
     * @return \Illuminate\Database\Connection
     */
    public static function resolveConnection($connection = null)
    {
        return static::getConnectionResolver()->connection($connection);
    }

    /**
     * 在协程环境下启用协程隔离连接
     * 自动为当前协程创建独立的数据库连接
     *
     * @param string|null $connection
     * @return void
     */
    public static function useCoroutineConnection($connection = null)
    {
        // 检查是否安装了 Swoole 扩展
        if (!extension_loaded('swoole') || !class_exists('\\Swoole\\Coroutine')) {
            return;
        }
        
        if (Coroutine::getCid() > 0) {
            $context = Coroutine::getContext();
            $contextKey = 'coroutine_connection_' . ($connection ?: 'default');
            
            // 检查协程上下文中是否已有连接
            if (!isset($context[$contextKey])) {
                // 获取数据库管理器并创建新的解析器实例
                $db = app('db');
                $newResolver = clone $db;
                
                // 清除现有连接，强制创建新连接
                $connectionName = $connection ?: config('database.default');
                $newResolver->purge($connectionName);
                
                // 设置新的连接解析器到协程上下文
                static::setConnectionResolver($newResolver);
                
                $context[$contextKey] = true;
            }
        }
    }

    /**
     * 重写newQuery方法，自动启用协程连接
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newQuery()
    {
        // 检查是否安装了 Swoole 扩展
        if (extension_loaded('swoole') && class_exists('\\Swoole\\Coroutine')) {
            // 在协程环境下自动启用协程连接
            if (Coroutine::getCid() > 0) {
                static::useCoroutineConnection();
            }
        }

        return parent::newQuery(...func_get_args());
    }

    /**
     * 重写构造函数，自动启用协程连接
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        // 检查是否安装了 Swoole 扩展
        if (extension_loaded('swoole') && class_exists('\\Swoole\\Coroutine')) {
            // 在协程环境下自动启用协程连接
            if (Coroutine::getCid() > 0) {
                static::useCoroutineConnection();
            }
        }

        parent::__construct($attributes);
    }
}
