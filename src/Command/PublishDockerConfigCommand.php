<?php

declare(strict_types=1);

namespace SwapCloud\CustomExtend\Command;

use Illuminate\Console\Command;

/**
 * Docker 开发环境快速启动
 */
class PublishDockerConfigCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'publish:docker {--port=8000 : 绑定宿主机的端口}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '发布docker配置文件到项目';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $base_path = base_path();
        $this->info("项目目录:{$base_path}");
        $file_base_path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR;
        $this->info("资源目录:{$file_base_path}");

        // nginx
        copy($file_base_path . 'default.conf', $base_path . DIRECTORY_SEPARATOR . 'default.conf');
        $this->info("Nginx配置发布成功");

        // dockerfile
        file_put_contents($base_path . DIRECTORY_SEPARATOR . 'Dockerfile', file_get_contents($file_base_path . 'Dockerfile'));
        $this->info("Dockerfile发布成功");

        // docker-compose
        $port = $this->option('port');
        $dockerComposeContent = file_get_contents($file_base_path . 'docker-compose.yml');
        $dockerComposeContent = str_replace('8000', $port, $dockerComposeContent);
        file_put_contents($base_path . DIRECTORY_SEPARATOR . 'docker-compose.yml', $dockerComposeContent);
        $this->info("Docker-Compose配置发布成功");

        $this->info("使用的端口: $port");
    }
}
