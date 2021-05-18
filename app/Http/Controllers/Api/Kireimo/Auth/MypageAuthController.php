<?php
namespace App\Http\Controllers\Api\Kireimo\Auth;

use Illuminate\Http\Request;
use App\Model\Kireimo\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Service\UtilService;

class MaypageAuthController extends Controller
{
    public function index(Request $request, Customer $customerModel)
    {
        $validator = Validator::make($request->all(), [
            'customerNo' => 'required | string | max:11',
            'password' => 'required | string | max:24',
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
        $customer = $customerModel->getCustomerByNo($validatedData['customerNo']);

        // パスワード認証
        if (empty($customer) || ($validatedData['password'] !== $customer['password']) || ($customer->ctype !== 1 && $customer->ctype !== 101)) {
            // 顧客が存在しない 又は パスワードが一致しない 又は 一般会員でもテスト会員でない
            return response()->json([
                'apiStatus' => 200,
                'body' => [
                    'authResult' => false
                ]
            ]);
        }

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'body' => [
                'authResult' => true,
                'customerId' => $customer->id
            ]
        ]);
    }

}

