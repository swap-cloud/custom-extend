<?php

namespace SwapCloud\CustomExtend\ProxyTools;

use Illuminate\Support\Collection;
/**
 * 文件工具类
 */
class FIleTools
{
    public function __construct()
    {

    }

    /**
     * 创建文件
     *
     * @param Collection $task
     * @return array
     */
    public function createFile(Collection $task)
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
    public function readFile(Collection $task)
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
    public function editFile(Collection $task)
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
    public function deleteFile(Collection $task)
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
    public function createDir(Collection $task)
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
    public function getDirTree(Collection $task)
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
    public function getDirectoryTree($directory, $excludeDirs = [], $recursion = false, $level = 0)
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
    public function deleteDir(Collection $task)
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
    public function findInFile(Collection $task)
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
    public function searchInFile(string $filePath, string $pattern, int $scope): array
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
