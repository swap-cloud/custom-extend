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
    protected $signature = 'cloud-proxy:run {projectId}';

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


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->projectId = $this->argument('projectId');
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
        try {
            // 执行任务
            switch ($task->get('command')) {
                case 'create_file':
                    [$state, $response] = $this->createFile($task);
                    break;
                case 'read_file':
                    [$state, $response] = $this->readFile($task);
                    break;
                case 'edit_file':
                    [$state, $response] = $this->editFile($task);
                    break;
                case 'delete_file':
                    [$state, $response] = $this->deleteFile($task);
                    break;
                case 'create_dir':
                    [$state, $response] = $this->createDir($task);
                    break;
                case 'get_dir_tree':
                    [$state, $response] = $this->getDirTree($task);
                    break;
                case 'delete_dir':
                    [$state, $response] = $this->deleteDir($task);
                    break;
                case 'find_in_file':
                    [$state, $response] = $this->findInFile($task);
                    break;
                default:
                    [$state, $response] = ['failed', ['message' => 'Unknown command: ' . $task->get('command')]];
                    break;
            }
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

    /**
     * 创建文件
     *
     * @param Collection $task
     * @return array
     */
    private function createFile(Collection $task)
    {
        // 将任务请求数据转换为集合对象
        $requestData = new Collection($task->get('request'));
        $filePath = $requestData->get('file_path');
        $content = $requestData->get('content', '');
        $fullPath = base_path($filePath);

        // 检查文件是否已存在
        if (file_exists($fullPath)) {
            return ['fail', [
                'message' => 'File already exists',
                'path' => $filePath,
            ]];
        }

        // 确保目录存在
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                return ['fail', [
                    'message' => 'Failed to create directory',
                    'path' => $directory,
                ]];
            }
        }

        // 创建文件
        if (file_put_contents($fullPath, $content) === false) {
            return ['fail', [
                'message' => 'Failed to create file',
                'path' => $filePath,
            ]];
        }

        return ['success', [
            'message' => 'File created successfully',
            'path' => $filePath,
        ]];
    }

    /**
     * 读取文件
     *
     * @param Collection $task
     * @return array
     */
    private function readFile(Collection $task)
    {
        try {
            // 将任务请求数据转换为集合对象
            $requestData = new Collection($task->get('request'));
            $filePath = $requestData->get('file_path');
            $startLine = intval($requestData->get('start_line', -1));
            $endLine = intval($requestData->get('end_line', -1));
            $fullPath = base_path($filePath);

            $content = file_get_contents($fullPath);
            if ($startLine != -1 && $endLine != -1) {
                $lines = explode("\n", $content);
                $content = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
            }
            return ['success', [
                'message' => 'File read successfully',
                'content' => $content
            ]];
        } catch (\Exception $e) {
            return ['fail', [
                'message' => 'Failed to read file: ' . $e->getMessage(),
                'path' => $filePath,
            ]];
        }
    }

    /**
     * 编辑文件
     *
     * @param Collection $task
     * @return array
     */
    private function editFile(Collection $task)
    {
        // 将任务请求数据转换为集合对象
        $requestData = new Collection($task->get('request'));
        $filePath = $requestData->get('file_path');
        $startLine = intval($requestData->get('start_line', 0));
        $endLine = intval($requestData->get('end_line', 0));
        $newContent = $requestData->get('content', '');
        $fullPath = base_path($filePath);

        // 验证文件是否存在
        if (!file_exists($fullPath)) {
            return ['fail', [
                'message' => 'File not found',
                'path' => $filePath,
            ]];
        }

        // 如果 startLine 为 0 且 endLine 为 -1，则替换整个文件
        if ($endLine === -1) {
            file_put_contents($fullPath, $newContent);
        } else {
            $fileContentLines = explode("\n", file_get_contents($fullPath));
            $totalLines = count($fileContentLines);

            // 将新内容按行分割
            $newContentLines = explode("\n", $newContent);

            // 构建新的文件内容
            $resultLines = [];

            // 添加 startLine 之前的内容
            for ($i = 0; $i < $startLine - 1 && $i < $totalLines; $i++) {
                $resultLines[] = $fileContentLines[$i];
            }

            // 添加新内容
            foreach ($newContentLines as $line) {
                $resultLines[] = $line;
            }

            // 添加 endLine 之后的内容
            for ($i = $endLine; $i < $totalLines; $i++) {
                $resultLines[] = $fileContentLines[$i];
            }

            file_put_contents($fullPath, implode("\n", $resultLines));
        }

        return ['success', [
            'message' => 'File edit successfully',
            'path' => $filePath,
        ]];
    }

    /**
     * 删除文件
     *
     * @param Collection $task
     * @return array
     */
    private function deleteFile(Collection $task)
    {
        // 将任务请求数据转换为集合对象
        $requestData = new Collection($task->get('request'));
        $filePath = $requestData->get('file_path');
        $fullPath = base_path($filePath);

        if (!file_exists($fullPath)) {
            return ['fail', [
                'message' => 'File not found',
                'path' => $filePath,
            ]];
        }

        if (!is_writable($fullPath)) {
            return ['fail', [
                'message' => 'File is not writable',
                'path' => $filePath,
            ]];
        }

        if (!unlink($fullPath)) {
            return ['fail', [
                'message' => 'Failed to delete file',
                'path' => $filePath,
            ]];
        }

        return ['success', [
            'message' => 'File delete successfully',
            'path' => $filePath,
        ]];
    }

    /**
     * 创建目录
     *
     * @param Collection $task
     * @return array
     */
    private function createDir(Collection $task)
    {
        // 将任务请求数据转换为集合对象
        $requestData = new Collection($task->get('request'));
        $path = $requestData->get('path', '/');
        $fullPath = base_path($path);

        if (file_exists($fullPath)) {
            return ['fail', [
                'message' => 'Path already exists',
                'path' => $path,
            ]];
        }

        if (!mkdir($fullPath, 0755, true)) {
            return ['fail', [
                'message' => 'Failed to create directory',
                'path' => $path,
            ]];
        }

        return ['success', [
            'message' => 'Dir create successfully',
            'path' => $path,
        ]];
    }

    /**
     * 获取目录树
     *
     * @param Collection $task
     * @return array
     */
    private function getDirTree(Collection $task)
    {
        // 将任务请求数据转换为集合对象
        $requestData = new Collection($task->get('request'));
        $path = $requestData->get('path', '/');
        $recursion = $requestData->get('recursion', false);
        $fullPath = base_path($path);
        $ignoreDirs = $requestData->get('ignore_dirs', []);

        $tree = $this->getDirectoryTree($fullPath, $ignoreDirs, $recursion);
        return ['success', [
            'message' => 'Dir tree get successfully',
            'base_path' => $path,
            'dir_tree' => $tree,
        ]];
    }


    /**
     * 递归获取目录结构树，排除指定目录
     *
     * @param string $directory
     * @param array $excludeDirs
     * @param int $level
     * @return array
     */
    private function getDirectoryTree($directory, $excludeDirs = [], $recursion = false, $level = 0)
    {
        $result = [];
        $files = scandir($directory);
        // 过滤掉 '.' 和 '..'
        $filteredFiles = array_filter($files, function ($file) {
            return $file !== '.' && $file !== '..';
        });
        $fileCount = count($filteredFiles);
        $currentIndex = 0;

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $currentIndex++;

            $path = $directory . DIRECTORY_SEPARATOR . $file;
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);

            // 检查是否需要排除此目录
            $shouldExclude = false;
            foreach ($excludeDirs as $excludeDir) {
                if (strpos($relativePath, $excludeDir) === 0) {
                    $shouldExclude = true;
                    break;
                }
            }

            if ($shouldExclude) {
                continue;
            }

            $isLast = ($currentIndex == $fileCount);
            $prefix = $isLast ? '└── ' : '├── ';
            $indent = '';
            for ($i = 0; $i < $level; $i++) {
                $indent .= '    ';
            }
            $item = $indent . $prefix . $file;

            if (is_dir($path)) {
                $item .= '/';
                $result[] = $item;
                // 只有在递归模式下才继续遍历子目录
                if ($recursion) {
                    $subTree = $this->getDirectoryTree($path, $excludeDirs, $recursion, $level + 1);
                    $result = array_merge($result, $subTree);
                }
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * 删除目录
     *
     * @param Collection $task
     * @return array
     */
    private function deleteDir(Collection $task)
    {
        // 将任务请求数据转换为集合对象
        $requestData = new Collection($task->get('request'));
        $path = $requestData->get('path', '/');
        $fullPath = base_path($path);

        if (!file_exists($fullPath)) {
            return ['fail', [
                'message' => 'Directory not found',
                'path' => $path,
            ]];
        }

        if (!is_dir($fullPath)) {
            return ['fail', [
                'message' => 'Path is not a directory',
                'path' => $path,
            ]];
        }

        if (!rmdir($fullPath)) {
            return ['fail', [
                'message' => 'Failed to delete directory (may not be empty)',
                'path' => $path,
            ]];
        }

        return ['success', [
            'message' => 'Dir delete successfully',
            'path' => $path,
        ]];
    }
    /**
     * 在路径内的文件或者再文件内进行内容中搜索
     *
     * @param Collection $task
     * @return array
     */
    private function findInFile(Collection $task)
    {
        // 将任务请求数据转换为集合对象
        $requestData = new Collection($task->get('request'));
        $path = $requestData->get('file_path');
        $scope = intval($requestData->get('scope'));
        $pattern = strval($requestData->get('pattern'));
        $fullPath = base_path($path);
        $ignoreDirs = $requestData->get('ignore_dirs', []);

        $results = [];

        if (is_file($fullPath)) {
            // 处理单个文件
            $results = $this->searchInFile($fullPath, $pattern, $scope);
        } elseif (is_dir($fullPath)) {
            // 处理目录
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($fullPath));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $shouldExclude = false;
                    foreach ($ignoreDirs as $excludeDir) {
                        if (strpos($relativePath, $excludeDir) === 0) {
                            $shouldExclude = true;
                            break;
                        }
                    }

                    if (!$shouldExclude) {
                        $fileResults = $this->searchInFile($file->getPathname(), $pattern, $scope);
                        $results = array_merge($results, $fileResults);
                    }
                }
            }
        } else {
            return ['fail', [
                'message' => 'Path does not exist or is not accessible',
                'path' => $path,
            ]];
        }
        return ['success', [
            'message' => 'Find in file successfully',
            'results' => $results,
        ]];
    }

    /**
     * 在单个文件中搜索模式并返回匹配行及其上下文
     *
     * @param string $filePath
     * @param string $pattern
     * @param int $scope
     * @return array
     */
    private function searchInFile(string $filePath, string $pattern, int $scope): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }

        $lines = file($filePath);
        if ($lines === false) {
            return [];
        }
        $results = [];

        // 确保正则表达式有正确的分隔符，并处理无效的正则表达式
        $delimitedPattern = $pattern;
        if (@preg_match($delimitedPattern, '') === false) {
            $delimitedPattern = '/' . preg_quote($pattern, '/') . '/';
        }

        foreach ($lines as $lineNumber => $lineContent) {
            if (@preg_match($delimitedPattern, $lineContent)) {
                $start = max(0, $lineNumber - $scope);
                $end = min(count($lines) - 1, $lineNumber + $scope);
                $contextLines = array_slice($lines, $start, $end - $start + 1);

                $results[] = [
                    'file_path' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $filePath),
                    'line_number' => $lineNumber + 1,
                    'line_content' => rtrim($lineContent, "\r\n"),
                    'context' => rtrim(implode("", $contextLines)),
                ];
            }
        }

        return $results;
    }
}
