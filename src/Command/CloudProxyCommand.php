<?php

namespace SwapCloud\CustomExtend\Command;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 代理Command
 */
class CloudProxyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cloud-proxy:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run cloud proxy tasks';
    /**
     * 网关
     */
    protected string $gateway = 'https://cloud-proxy.itxiao6.top';
    /**
     * 项目标识
     */
    protected $projectId;
    protected array $toolsAdapters = [
        'file' => \SwapCloud\CustomExtend\ProxyTools\FileTools::class,
        'terminal' => \SwapCloud\CustomExtend\ProxyTools\TerminalTools::class,
    ];


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (strlen(strval(env('CLOUD_PROXY_GATEWAY', ''))) >= 1) {
            $this->gateway = env('CLOUD_PROXY_GATEWAY');
        }
        $this->projectId = env('CLOUD_PROXY_PROJECT_ID');
        if (strlen($this->projectId) <= 0) {
            $this->error('项目ID不能为空');
            return 1;
        }
        $this->info("网关：{$this->gateway} 项目ID：{$this->projectId} 开始运行");
        while (true) {
            try {
                $this->runPendingTasks();
            } catch (\Exception $e) {
                $this->error('运行任务失败：' . $e->getMessage());
            }
            // 延迟五百毫秒
            usleep(500000);
        }
    }
    protected function runPendingTasks()
    {
        // 调用API领取任务
        $client = new Client([
            'base_uri' => $this->gateway,
        ]);
        $response = $client->get('/api/task/pop', [
            'query' => [
                'project_id' => $this->projectId,
            ]
        ]); // 替换为实际的API地址
        $data = json_decode($response->getBody(), true);
        if ($data['status'] == 201) {
            return;
        }
        if ($data['status'] != 200) {
            $this->error('领取任务失败：' . $data['message']);
            return;
        }
        // 将任务数据转换为集合对象
        $task = new Collection($data['data']);
        $this->info("开始执行任务: {$task->get('id')} 、命令：{$task->get('command')}");

        $commandParts = explode('.', $task->get('command'));
        $commandClass = $commandParts[0]; // file terminal
        $commandMethod = $commandParts[1]; // createFile

        try {
            // 执行任务
            $toolAdapter = $this->toolsAdapters[$commandClass] ?? null;
            if (!$toolAdapter) {
                throw new \Exception("未知的命令类: {$commandClass}");
            }
            $tool = new $toolAdapter();
            $result = $tool->$commandMethod($task);
            $state = $result['state'] ?? 'fail';
            $response = $result['response'] ?? ['error' => 'Unknown error occurred'];
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $state = 'fail';
            $response = ['error' => $e->getMessage()];
        }
        // Ensure $state and $response are defined
        if (!isset($state)) {
            $state = 'fail';
        }
        if (!isset($response)) {
            $response = ['error' => 'Unknown error occurred'];
        }
        $this->info("任务执行完成: {$task->get('id')} 、状态：{$state} 、结果：" . json_encode($response));
        // 调用API反馈任务结果
        $response = json_decode($client->post('/api/task/result', [
            'json' => [
                'task_id' => $task->get('id'),
                'project_id' => $this->projectId,
                'response' => $response,
                'state' => $state,
            ],
        ])->getBody()->getContents(), true);
    }
}
