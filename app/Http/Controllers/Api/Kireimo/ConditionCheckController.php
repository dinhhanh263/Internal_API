<?php
namespace App\Http\Controllers\Api\Kireimo;

use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Model\Kireimo\Contract;
use App\Model\Kireimo\Course;
use App\Model\Kireimo\Customer;
use App\Model\Kireimo\Reservation;
use App\Service\ConditionCheckService;
use App\Service\UtilService;
use App\Exceptions\MyApiException;

class ConditionCheckController extends Controller
{
    // 契約コンディションチェック
    public function index($contractId, ConditionCheckService $conditionCheckService, Contract $contractModel, Customer $customerModel, Reservation $reservationModel, Course $courseModel) {
        // バリデーションチェック
        $validator = Validator::make(["contractId" => $contractId], [
            'contractId' => 'required | integer'
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

        // 契約情報取得
        $contractResult = $contractModel->getContract($contractId);
        if (empty($contractResult)) {
            // エラーレスポンス返却
            return response()->json([
                'apiStatus' => 400,
                'errorReasonCd' => "E5002",
                'errorKey' => 'contractId'
            ]);
        }
        // コース情報取得
        $courseResult = $courseModel->getCourse($contractResult->course_id);
        if (empty($courseResult)) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002");
        }

        // 顧客情報取得
        $customerResult = $customerModel->getCustomerById($contractResult->customer_id);
        if (empty($customerResult)) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002");
        }

        // 不正コンディション一覧取得
        $failedConditions = $conditionCheckService->getFailedConditions($customerResult, $contractResult, $courseResult);

        // パック最終予約フラグ
        $packLastReservationFlg = false;
        if ($courseResult->type === 0 && $contractResult->times !=0 && $contractResult->r_times >= $contractResult->times) {
            $packLastReservationFlg = true;
        }

        $responseBody = [];
        $responseBody['failedConditions'] = $failedConditions;
        $responseBody['packLastReservationFlg'] = $packLastReservationFlg;

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'body' => $responseBody
        ]);
    }
}

