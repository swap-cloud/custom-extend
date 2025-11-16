<?php

namespace SwapCloud\CustomExtend\ProxyTools;

class TerminalTools
{
    /**
     * 获取当前操作系统 版本 架构
     * @param $task
     * @return array
     */
    public function getPlatform($task)
    {
        // 获取当前操作系统 版本 架构
        $platform = [
            'os' => php_uname('s'),
            'name' => php_uname('n'),
            'version' => php_uname('r'),
            'architecture' => php_uname('m'),
            'version' => php_uname('v'),
        ];
        return ['success', [
            'message' => 'Platform information',
            'platform' => $platform,
        ]];
    }
    /**
     * 执行命令
     * @param $task
     * @return array
     */
    public function exec($task)
    {
        $requestData = collect($task->get('request', []));
        $command = $requestData->get('command', ''); // 示例 ls -ahl \npwd \n tree .
        $sync = $requestData->get('sync', true);
        // 返回信息
        $result = [];

        // $command = "cd " . base_path() . "\n" . $command;
        // dump(explode("\n", $command));
        // Split commands by newline
        $commands = explode("\n", $command);
        $response = [
            'process_id' => '',
            'output' => '',
            'error' => '',
            'exit_code' => null
        ];
        
        if ($sync) {
            // 同步执行 - execute each command sequentially
            foreach ($commands as $cmd) {
                if (empty(trim($cmd))) continue; // Skip empty lines
                $commandParts = str_getcsv(trim($cmd), ' '); // This handles spaces in arguments correctly
                $process = new \Symfony\Component\Process\Process($commandParts);
                $process->run();
                // Ensure proper UTF-8 encoding
                $output = mb_convert_encoding($process->getOutput(), 'UTF-8', 'UTF-8');
                $error = mb_convert_encoding($process->getErrorOutput(), 'UTF-8', 'UTF-8');
                $response['output'] .= $output;
                $response['error'] .= $error;
                $response['exit_code'] = $process->getExitCode();
                
                // If any command fails, stop execution
                if ($process->getExitCode() !== 0) {
                    break;
                }
            }
        } else {
            // 异步执行 - only execute the first command
            if (!empty($commands)) {
                $firstCommand = trim($commands[0]);
                if (!empty($firstCommand)) {
                    $commandParts = str_getcsv($firstCommand, ' '); // This handles spaces in arguments correctly
                    $process = new \Symfony\Component\Process\Process($commandParts);
                    $process->start(function ($type, $buffer) use (&$response) {
                        // Ensure proper UTF-8 encoding
                        $buffer = mb_convert_encoding($buffer, 'UTF-8', 'UTF-8');
                        if ('err' === $type) {
                            $response['error'] .= $buffer;
                        } else {
                            $response['output'] .= $buffer;
                        }
                    });
                    $response['process_id'] = $process->getPid();
                }
            }
        }

        return ['success', [
            'message' => 'Command executed successfully',
            'response' => $response,
        ]];
    }
    /**
     * 查询异步命令执行结果
     * @param $task
     * @return array
     */
    public function result($task)
    {
        $requestData = collect($task->get('request', []));
        // 异步执行标识
        $processId = intval($requestData->get('process', ''));
        // 查询结果
        $result = [];

        // 根据进程ID查询结果
        if ($processId > 0) {
            // 使用posix_kill检查进程是否存在
            if (function_exists('posix_kill') && posix_kill($processId, 0)) {
                $result['status'] = 'running';
            } else {
                // 进程不存在，可能已完成
                $result['status'] = 'finished';
                // 从任务响应中获取输出
                $response = $task->response ?? [];
                // Ensure proper UTF-8 encoding
                $result['output'] = mb_convert_encoding($response['output'] ?? '', 'UTF-8', 'UTF-8');
                $result['error'] = mb_convert_encoding($response['error'] ?? '', 'UTF-8', 'UTF-8');
                $result['exit_code'] = $response['exit_code'] ?? null;
            }
        } else {
            $result['error'] = 'Invalid process ID';
        }

        return [
            'success',
            'message' => 'Command get result successfully',
            'result' => $result,
        ];
    }
    /**
     * 停止Ctrl+C 信号
     */
    public function kill($task)
    {
        $requestData = collect($task->get('request', []));
        // 异步执行标识
        $processId = intval($requestData->get('process', ''));
        // 根据进程ID终止进程
        $result = [];

        if ($processId > 0) {
            if (function_exists('posix_kill')) {
                if (posix_kill($processId, 15)) { // SIGTERM
                    $result['message'] = 'Process termination signal sent';
                } else {
                    $result['error'] = 'Failed to send termination signal';
                }
            } else {
                $result['error'] = 'POSIX functions not available';
            }
        } else {
            $result['error'] = 'Invalid process ID';
        }

        return ['success', [
            'message' => 'Process killed successfully',
            'result' => $result,
        ]];
    }
}
