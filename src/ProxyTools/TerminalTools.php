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
            'version' => php_uname('r'),
            'architecture' => php_uname('m')
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
        $requestData = collect($task->get('request',[]));
        $command = $requestData->get('command',''); // 示例 ls -ahl \npwd \n tree .
        $sync = $requestData->get('sync',false);
        // 返回信息
        $result = [];

        $command = "cd ".base_path()."\n".$command;

        // 引入 Symfony Process 组件
        $process = new \Symfony\Component\Process\Process(explode("\n", $command));

        if ($sync) {
            // 同步执行
            $process->run();
            $result['output'] = $process->getOutput();
            $result['error'] = $process->getErrorOutput();
            $result['exit_code'] = $process->getExitCode();
        } else {
            // 异步执行
            $process->start();
            // 将进程PID保存到任务中，以便后续查询
            $task->response = [
                'process_id' => $process->getPid(),
                'output' => '',
                'error' => '',
                'exit_code' => null
            ];
            $task->save();
            $result['process_id'] = $process->getPid();
        }

        return ['success', [
            'message' => 'Command executed successfully',
            'command' => $command,
            'sync' => $sync,
            'result' => $result,
        ]];
    }
    /**
     * 查询异步命令执行结果
     * @param $task
     * @return array
     */
    public function result($task)
    {
        $requestData = collect($task->get('request',[]));
        // 异步执行标识
        $processId = intval($requestData->get('process',''));
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
                $result['output'] = $response['output'] ?? '';
                $result['error'] = $response['error'] ?? '';
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
        $requestData = collect($task->get('request',[]));
        // 异步执行标识
        $processId = intval($requestData->get('process',''));
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
