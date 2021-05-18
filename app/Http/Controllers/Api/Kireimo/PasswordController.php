<?php

namespace App\Http\Controllers\Api\Kireimo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Model\Kireimo\Customer;
use App\Service\UtilService;
use App\Exceptions\MyApiException;

class PasswordController extends Controller
{
    // パスワード変更
    public function put(Request $request, $customerId, Customer $customerModel) {
        // バリデーションチェック
        $validator = Validator::make($request->all(), [
            'password' => 'required | string | max:24'
        ], UtilService::getValidateMessage());

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

        // DB接続
        $resultCount = $customerModel
        ->where('id', $customerId)
        ->where('del_flg', 0)
        ->update(['password' => $validatedData['password']]);

        if ($resultCount == 0) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002");
        }

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200
        ]);
    }

}
