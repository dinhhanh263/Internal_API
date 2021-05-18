<?php

namespace App\Http\Controllers\Api\Kireimo\Action;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\MyApiException;
use App\Http\Controllers\Controller;
use App\Model\Kireimo\Shop;
use App\Service\CustomerService;
use App\Service\ReservationService;
use App\Service\UtilService;
use App\Rules\MyEmail;
use App\Rules\Kana;
use App\Rules\SpaceInvalid;

class CounselingController extends Controller
{

    public function register(Request $request, Shop $shopModel, CustomerService $customerService, ReservationService $reservationService)
    {
        // バリデーションチェック
        $validator = Validator::make($request->all(), [
            'adcode' => 'string',
            'inviteCustomerNo' => 'string',
            'reservation' => 'required',
            'reservation.reservationType' => 'required | integer | between:1,1',
            'reservation.shopId' => 'required | integer',
            'reservation.hopeDate' => 'required | string | date_format:Y-m-d',
            'reservation.hopeTimeCd' => ['required', 'regex:/^(1|3|5|7|9|11|12|13|15|17|19|){1}$/'],
            'reservation.memo' => 'string',
            'customer' => 'required',
            'customer.name1' => ['required', 'string', 'max:25', new SpaceInvalid],
            'customer.name2' => ['required', 'string', 'max:25', new SpaceInvalid],
            'customer.nameKana1' => ['required', 'string', 'max:25', new SpaceInvalid, new Kana],
            'customer.nameKana2' => ['required', 'string', 'max:25', new SpaceInvalid, new Kana],
            'customer.birthday' => 'required | string | date_format:Y-m-d',
            'customer.tel' => ['required', 'between:12,13', 'regex:/^0\d+-\d+-\d+$/'],
            'customer.mail' => ['required', 'string', 'max:63', new MyEmail],
//             'customer.zip' => ['between:8,8', 'regex:/^[0-9-]*$/'],
//             'customer.prefCd' => 'integer | digits_between:1,2',
//             'customer.address' => 'string | nullable',
            'customer.hopesDiscountFlg' => 'boolean',
            'customer.hopeCampaign' => 'string'
        ], UtilService::getValidateMessage());

        // バリデーションエラー処理
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

        // ショップ情報取得
        $shopResult = $shopModel->getShopInfo($request->get("reservation")['shopId']);
        if (empty($shopResult)) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002", "reservation.shopId");
        }

        \DB::beginTransaction();
        try {
            // 顧客登録
            $customerObject = $customerService->register($request->get("customer"), $shopResult, $validatedData['inviteCustomerNo'] ?? null, $validatedData['adcode'] ?? null);

            // 予約登録
            $reservationId = $reservationService->registCounselingReserve($request->get("reservation"), $customerObject, $shopResult, $validatedData['inviteCustomerNo'] ?? null, $validatedData['adcode'] ?? null);

            \DB::commit();

        } catch (\Exception $e){
            \DB::rollBack();

            // エラーレスポンス返却
            throw $e;
        }

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'customerId' => $customerObject->id,
            'reservationId' => $reservationId,
            'rebookFlg' => $customerObject->rebook_flg ?? 0
        ]);

    }
}
