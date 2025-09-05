<?php

declare(strict_types=1);

namespace SwapCloud\CustomExtend\Traits;


/**
 * API工具类
 */
trait ApiResponseTrait
{
    /**
     * 成功
     * @param string $message
     * @param mixed $data
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success(string $message = '操作成功', mixed $data = [], int $status = 200): \Illuminate\Http\JsonResponse
    {
        return $this->jsonResponse($status, $message, $data);
    }


    /**
     * @param string $message
     * @param mixed $data
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    protected function fail(string $message = '操作失败', mixed $data = [], int $status = 400): \Illuminate\Http\JsonResponse
    {
        return $this->jsonResponse($status, $message, $data);
    }

    /**
     * @param int $status
     * @param string $message
     * @param $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function jsonResponse(int $status, string $message, $data)
    {
        return response()->json([
            'status' => $status,
            'success' => $status === 200,
            'message' => $message,
            'data' => $data ?? [],
        ])->withHeaders([
            'Access-Control-Allow-Origin' => \request()->header('Origin') ?: '*',
            'Access-Control-Allow-Credentials' => (\request()->header('Origin') ?: '*')=='*'?'false':'true',
            'Access-Control-Allow-Headers' => 'Accept, Content-Type, Authorization, X-Requested-With, locale'
        ]);
    }
}
