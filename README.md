### 中继owl-admin、增加自定义组件

##### 安装本扩展
```bash
composer require swap-cloud/custom-extend
```

#### 1. 为了方便初学者避免遇到php 扩展以及配置问题，推荐使用docker-compose 作为环境编排。`运行环境需要安装docker`

>  a. 发布配置
```bash
php artisan publish:docker
```
>
>  b. 启动环境
```bash
docker-compose up -d
``` 
> 
>  如需进入docker 环境内 运行环境，调试命令 请使用  即可进入容器内终端
```bash
docker-compose exec api bash
```
> 
>  如需修改访问端口 则在 项目目录下的 docker-compose.yml内修改 services->nginx->ports 的 8000 为你要使用的端口，默认端口为 ：localhost:8000/admin


#### 2. 为了解决字典自动加载，以及扩展更新时 新增的Migration文件。使用\SwapCloud\CustomExtend\Extend\SwapCloudServiceProvider作为基础服务提供者即可解决。具体使用方式参考如下

>
>  并且将扩展依赖加入到自己扩展的composer.json->require内。版本要求为：*
>
>  在自己扩展内的 src/XxxxServiceProvider.php内 修改继承类 为 SwapCloudServiceProvider 如下所示。

```php
<?php

namespace SwapCloud\Demo;

use SwapCloud\CustomExtend\Extend\SwapCloudServiceProvider;

/**
 * 扩展的服务提供者
 */
class DemoServiceProvider extends SwapCloudServiceProvider
{
    protected $menu = [
        [
            'parent' => '',
            'title' => '演示系统',
            'url' => '/demo',
            'url_type' => '1',
            'icon' => 'ant-design:file-zip-outlined',
        ]
    ];
    protected $dict = [
        [
            'key' => 'filesystem.driver',
            'value' => '文件系统驱动',
            'keys' => [
                [
                    'key' => 'local',
                    'value' => '本地存储'
                ],
                [
                    'key' => 'kodo',
                    'value' => '七牛云kodo'
                ],
                [
                    'key' => 'cos',
                    'value' => '腾讯云COS'
                ],
                [
                    'key' => 'oss',
                    'value' => '阿里云OSS'
                ]
            ]
        ]
    ];
    protected $permission = [
        [
            'name'=>'测试权限',
            'slug'=>'test',
            'method'=>[],// 空则代表ANY
            'path'=>[],// 授权接口
            'parent'=>'',// 父级权限slug字段
        ],
        [
            'name'=>'测试接口',
            'slug'=>'test',
            'method'=>[
                'POST',
                'GET',
            ],// 空则代表ANY
            'path'=>[
                '/test/api*'
            ],// 授权接口
            'parent'=>'test',// 父级权限slug字段
        ],
    ];
}
```

#### 3. 如需定义api 直接提供给外部使用，或者自定义鉴权机制的 则可以在 扩展目录下的src/Http/api_routes.php 定义路由。此文件默认不存在 需要自己创建。

> 新建路由文件 src/Http/api_routes.php
>
> 写入自己的路由 如下所示
> 
> 本路由可以使用 /demo 直接访问，与 `admin_api` 无关

```php
<?php

use Illuminate\Support\Facades\Route;
Route::any('/demo',function(){
    return ['msg'=>'HelloWorld'];
});
Route::get('/test',[SwapCloud\FileSystem\Http\Controllers\DemoApiController::class,'test']);
Route::get('/test1',[SwapCloud\FileSystem\Http\Controllers\DemoApiController::class,'test1']);
```


#### 4. API逻辑交互 也提供了 json 返回的工具类 

> 新建控制器，use ApiResponseTrait;即可通过$this 调用接口返回工具

```php
<?php

namespace SwapCloud\FileSystem\Http\Controllers;

use Illuminate\Routing\Controller;
use SwapCloud\CustomExtend\Traits\ApiResponseTrait;

/**
 *
 */
class DemoApiController extends Controller
{
    use ApiResponseTrait;
    public function test()
    {
        return $this->success('测试成功',[
            'list'=>[]
        ]);
    }
    public function test1()
    {
        return $this->fail('错误',[
            'list'=>[]
        ]);
    }
}
```



#### 5. 公共函数库

> 函数1 admin_user_role_check

```php
/**
 * 检查当前管理员用户 是否属于指定的角色
 * @param string|array $role
 * @return bool
 */
function admin_user_role_check(string|array $role):bool;
// 使用示例
admin_user_role_check('Administrator');// 检查是否是 超级管理员
admin_user_role_check(['Administrator','user-passs']);// 检查是否是 超级管理员 并且是 用户审核员
```


#### 5. LaravelSwoole 支持


##### 初始化
```bash
php artisan laravels publish
```

##### 常用操作
```bash
php bin/laravels {start|stop|restart|reload|info|help}
```