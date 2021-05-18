<?php
namespace app\Http\Controllers\Api\Auth;

use Illuminate\Http\Request;
use App\Model\Message\MailAuthority;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Service\UtilService;

class MailAdminAuthController extends Controller
{
    public function index(Request $request, MailAuthority $mailAuthorityModel)
    {
        $validator = Validator::make($request->all(), [
            'loginId' => 'required | string',
            'password' => 'required | string',
        ], UtilService::getValidateMessage());

        // バリデーションエラー時処理
        if ($validator->fails()) {
            foreach ($validator->errors()->getMessages() as $key => $value) {
                // エラーレスポンス返却
                return response()->json([
                    'apiStatus' => 400,
                    'errorReasonCd' => $value[0],
                    'errorKey' => $key
                ]);
                break;
            }
        }

        $validatedData = $request->all();
        $mailAuthorityResult = $mailAuthorityModel->getAuthInfo($validatedData['loginId']);

        // パスワード認証
        if (empty($mailAuthorityResult) || !password_verify($validatedData['password'], $mailAuthorityResult['password'])) {
            // ログインIDが存在しない 又は 一致ユーザなし
            return response()->json([
                'apiStatus' => 204,
                'body' => []
            ]);
        }

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'body' => [
                'kireimoAuthorityLevel' => $mailAuthorityResult->kireimo_authority_level,
                'groupCd' => $mailAuthorityResult->group_cd
            ]
        ]);
    }

}

