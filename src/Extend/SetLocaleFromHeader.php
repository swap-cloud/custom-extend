<?php

namespace SwapCloud\CustomExtend\Extend;

class SetLocaleFromHeader
{
    public function handle($request, \Closure $next)
    {
        // 从请求头获取语言设置
        if ($request->hasHeader('locale')) {
            $locale = $request->header('locale');
            // 检查是否是有效的语言选项
            $availableLocales = config('app.available_locales', ['en', 'zh_CN']);
            if (in_array($locale, $availableLocales)) {
                app()->setLocale($locale);
            }
        }

        return $next($request);
    }
}
