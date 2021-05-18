<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;

class RequestMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // ユニークID
        $uniqid = uniqid();

        // 開始時間
        $time_start = microtime(true);

        // リクエストログ出力
        $this->writeRequestLog($uniqid, $request);
        $response = $next($request);

        // 実行時間
        $time = microtime(true) - $time_start;

        // レスポンスログ出力
        $this->writeResponseLog($uniqid, $request, $response, $time);

        return $response;
    }

    private function writeRequestLog($uniqid, $request)
    {
        if(strpos($request->fullUrl(),'mail') !== false || strpos($request->fullUrl(),'sms') !== false || strpos($request->fullUrl(),'smtp') !== false){
            \Log::channel('requestlogs')->info("【Request:".$uniqid . "】 " . "SERVER_NAME:". $_SERVER['SERVER_NAME'] . ", " . $request->method(), ['url' => $request->fullUrl()]);
        } else {
            \Log::channel('requestlogs')->info("【Request:".$uniqid . "】 " . "SERVER_NAME:". $_SERVER['SERVER_NAME'] . ", " . $request->method(), ['url' => $request->fullUrl(), 'request' => $request->all()]);
        }
    }

    private function writeResponseLog($uniqid, $request, $response, $time)
    {
        if($response instanceof JsonResponse) {
            $newEncodingOptions = $response->getEncodingOptions() | JSON_UNESCAPED_UNICODE;
            $response->setEncodingOptions($newEncodingOptions);
        }

        if(strpos($request->fullUrl(),'shops') !== false){
            \Log::channel('requestlogs')->info("【Response:".$uniqid . "】 time:" . $time. ", httpStatus:".$response->status());
        } else {
            \Log::channel('requestlogs')->info("【Response:".$uniqid . "】 time:" . $time. ", httpStatus:".$response->status(). ", content:". $response->content());
        }
    }
}