<?php

namespace App\Http\Middleware;

use Closure;
use App\Model\Common\ApiToken;

class ApiTokenFilter
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
        $apiTokenModel = new ApiToken();

        // gipホワイトリスト(Azureカウンセリングサーバー等)
        $whiteGipArray = array("13.78.106.96", "13.73.27.49", "13.73.21.66", "13.73.21.140", "13.73.21.186", "13.73.27.30",
            "13.73.23.70", "13.73.21.101", "13.73.23.163", "13.73.21.200",
            "13.73.26.73", "13.73.30.113", "52.243.32.123", "52.243.37.163", "13.73.27.207", "13.78.9.45", "13.78.23.111"
            );

        // gip取得
        $requestGip = !empty($_SERVER["HTTP_X_FORWARDED_FOR"]) ? explode(':', $_SERVER["HTTP_X_FORWARDED_FOR"])[0] : "";

        if(strpos($_SERVER["REQUEST_URI"],'activetest') === false){ //activetest:疎通確認ページ
            // ホワイトリストに存在しない場合はトークンを必須にする
            if (empty($requestGip) || array_search(explode(':', $_SERVER["HTTP_X_FORWARDED_FOR"])[0], $whiteGipArray) === False) {

                $apiToken = $requestGip ? $apiTokenModel->getApiTokenInfoByGip($requestGip) : "";
                if (empty($apiToken)) {
                    // 管理者トークンチェック
                    if ($request->header('api-token') != $apiTokenModel->getApiTokenInfo('ADMIN')['value']) {
                        // エラーレスポンス返却
                        return response()->json([
                            'apiStatus' => 401,
                            'errorReasonCd' => "E5000",
                            'errorKey' => null
                        ]);
                    }
                } else {
                    // 外部利用者トークンチェック
                    if ($request->header('api-token') !== $apiToken->value) {
                        // エラーレスポンス返却
                        return response()->json([
                            'apiStatus' => 401,
                            'errorReasonCd' => "E5000",
                            'errorKey' => null
                        ]);
                    }
                }
            }
        }

        return $next($request);
    }
}
