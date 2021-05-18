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
use App\Service\ReservationService;

class ReservableRangeController extends Controller
{
    // 予約可能日付取得
    public function index($contractId, ConditionCheckService $conditionCheckService, ReservationService $reservationService, Contract $contractModel, Customer $customerModel, Reservation $reservationModel, Course $courseModel) {
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
        $contractResult = $contractModel->getActiveContract($contractId);
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

        // 過去契約も含めた最終施術取得
        $lastTreatment = $reservationService->getLastTreatmentWithOld($contractResult);

        $lastTreatmentHopeDate = $lastTreatment->hope_date ?? "";

        // 最終ペナルティ消化取得(現在の契約のみ)
        $lastCancelPenalty = $reservationModel->getLastCancelPenalty($contractResult->id);

        // basedate取得
        $baseDate = $conditionCheckService->getBaseDate($contractResult, $courseResult, $lastCancelPenalty, $lastTreatmentHopeDate);

        // baseDateが契約日より後の場合エラー
        if ($conditionCheckService->checkContractEndDate($contractResult, $courseResult, $baseDate) === false) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E9016");
        }

        // 最大予約可能日
        $maxDate = $conditionCheckService->getMaxDate($contractResult, $courseResult, $baseDate, $lastTreatmentHopeDate, $lastCancelPenalty);

        // end_dateを考慮した【最大予約可能日】が過去日付の場合エラー
        if ($maxDate < date('Y-m-d')) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E9016");
        }

        $responseBody = [];
        $responseBody['minDate'] = $baseDate;
        $responseBody['maxDate'] = $maxDate;

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'body' => $responseBody
        ]);
    }
}

