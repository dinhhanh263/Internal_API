<?php

namespace App\Http\Controllers\Api\Kireimo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Model\Kireimo\Contract;
use App\Model\Kireimo\Course;
use App\Model\Kireimo\Customer;
use App\Model\Kireimo\Reservation;
use App\Model\Kireimo\Sales;
use App\Model\Kireimo\Shop;
use App\Service\ConditionCheckService;
use App\Service\ReservationService;
use App\Service\UtilService;
use App\Exceptions\MyApiException;

class ReservationController extends Controller
{
    // 顧客予約一覧取得
    public function index($customerId, Reservation $reservationModel) {
        // バリデーションチェック
        $validator = Validator::make(["id" => $customerId], [
            'id' => 'required | integer '
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

        // DB接続
        $reservationCollection = $reservationModel->getFutureReservation((int)$customerId);
        if ($reservationCollection->count() < 1) {
            // レスポンス返却(0件)
            return response()->json([
                'apiStatus' => 204,
                'count' => 0
            ]);
        }

        // レスポンスBody生成
        $responseBody = [];
        foreach($reservationCollection as $key => $value) {
            $responseBody[$key]['reservationId'] = $value->id;
            $responseBody[$key]['contractId'] = $value->contract_id;
            $responseBody[$key]['reservationType'] = $value->type;
            $responseBody[$key]['courseTreatmentType'] = $value->course_treatment_type;
            $responseBody[$key]['courseName'] = $value->course_name;
            $responseBody[$key]['shopId'] = $value->shop_id;
            $responseBody[$key]['shopName'] = $value->shop_name;
            $responseBody[$key]['shopAddress'] = $value->shop_address;
            $responseBody[$key]['shopPref'] = $value->shop_pref;
            $responseBody[$key]['hopeDate'] = $value->hope_date;
            $responseBody[$key]['hopeTimeCd'] = $value->hope_time;
            $responseBody[$key]['length'] = $value->length;
            $responseBody[$key]['status'] = $value->status;
            $responseBody[$key]['delayTimeStatus'] = $value->delay_time_status;
            $responseBody[$key]['delayTimeRegDate'] = $value->delay_time_reg_date;
        }

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'count' => $reservationCollection->count(),
            'body' => $responseBody
        ]);
    }

    // 予約情報取得
    public function get($id, Reservation $reservationModel) {
        // バリデーションチェック
        $validator = Validator::make(["id" => $id], [
            'id' => 'required | integer '
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

        // DB接続
        $reservation = $reservationModel->getReservationById((int)$id);
        if (empty($reservation)) {
            // レスポンス返却(0件)
            return response()->json([
                'apiStatus' => 204,
                'errorReasonCd' => null,
                'errorKey' => null
            ]);
        }

        // レスポンスBody生成
        $responseBody = [];
        $responseBody['customerId'] = $reservation->customer_id;
        $responseBody['reservationType'] = $reservation->type;
        $responseBody['shopId'] = $reservation->shop_id;
        $responseBody['hopeDate'] = $reservation->hope_date;
        $responseBody['hopeTimeCd'] = $reservation->hope_time;
        $responseBody['length'] = $reservation->length;
        $responseBody['status'] = $reservation->status;

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'body' => $responseBody
        ]);

    }

    // 予約登録
    public function post(Request $request, Customer $customerModel, Shop $shopModel, Reservation $reservationModel, Contract $contractModel, Course $courseModel, Sales $salesModel, ReservationService $reservationService, ConditionCheckService $conditionCheckService)
    {
        // バリデーションチェック
        $validator = Validator::make($request->all(), [
            'customerId' => 'required | integer',
            'shopId' => 'required | integer',
            'hopeDate' => 'required | string | date_format:Y-m-d',
            'hopeTimeCd' => 'required | integer'
        ], UtilService::getValidateMessage());

        $validator->sometimes('hopeTimeCd', ['regex:/^(1|3|5|7|9|11|12|13|15|17|19|){1}$/'], function ($input) {
            return $input->reservationType === 1;
        });

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

        // 顧客情報取得
        $customerResult = $customerModel->getCustomerById($validatedData['customerId']);
        if (empty($customerResult)) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002", "customerId");
        }

        // ショップ情報取得
        $shopResult = $shopModel->getShopInfo($validatedData['shopId']);
        if (empty($shopResult)) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002", "shopId");
        }

        // カウンセリング予約登録
        $reservationId = $reservationService->registCounselingReserve($validatedData, $customerResult, $shopResult);

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'reservationId' => $reservationId
        ]);

    }


    // トリートメント予約登録
    public function postTreatment(Request $request, Customer $customerModel, Shop $shopModel, Reservation $reservationModel, Contract $contractModel, Course $courseModel, Sales $salesModel, ReservationService $reservationService, ConditionCheckService $conditionCheckService)
    {
        // バリデーションチェック
        $validator = Validator::make($request->all(), [
            'contractId' => 'integer | required',
            'shopId' => 'required | integer',
            'hopeDate' => 'required | string | date_format:Y-m-d',
            'hopeTimeCd' => 'required | integer'
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

        // 契約情報取得
        $contractResult = $contractModel->getActiveContract($validatedData['contractId']);
        if (empty($contractResult)) {
            // エラーレスポンス返却
            return response()->json([
                'apiStatus' => 400,
                'errorReasonCd' => "E5002",
                'errorKey' => 'contractId'
            ]);
        }
        // ショップ情報取得
        $shopResult = $shopModel->getShopInfo($validatedData['shopId']);
        if (empty($shopResult)) {
            // エラーレスポンス返却
            return response()->json([
                'apiStatus' => 400,
                'errorReasonCd' => "E5002",
                'errorKey' => 'shopId'
            ]);
        }
        // 顧客情報取得
        $customerResult = $customerModel->getCustomerById($contractResult->customer_id);
        if (empty($customerResult)) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002");
        }
        // コース情報取得
        $courseResult = $courseModel->getCourse($contractResult->course_id);
        if (empty($courseResult) || $courseResult->treatment_type === 2) { // 整体は予約不可
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002");
        }

        // hopeTimeCdバリデーションチェック
        if ($courseResult->weekdays_plan_type === 0) {
            if (!array_key_exists($validatedData['hopeTimeCd'], config('myConfig.tokutoku_90_room_time_list'))) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E9003", "hopeTimeCd");
            }
        } elseif ($courseResult->length === 1) {
            if (!array_key_exists($validatedData['hopeTimeCd'], config('myConfig.30_room_time_list'))) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E9003", "hopeTimeCd");
            }
        } elseif ($courseResult->length === 2) {
            if (!array_key_exists($validatedData['hopeTimeCd'], config('myConfig.60_room_time_list'))) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E9003", "hopeTimeCd");
            }
        } elseif ($courseResult->length === 3) {
            if (!array_key_exists($validatedData['hopeTimeCd'], config('myConfig.90_room_time_list'))) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E9003", "hopeTimeCd");
            }
        } else {
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002", null);
        }

        // 契約期間(end_date)チェック
        if ($conditionCheckService->checkContractEndDate($contractResult, $courseResult, $validatedData['hopeDate']) === false) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E9016", "hopeDate");
        }

        // コンディションチェック
        // 不正コンディション一覧取得
        $failedConditions = $conditionCheckService->getFailedConditions($customerResult, $contractResult, $courseResult);
        if (!empty($failedConditions)) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E9014");
        }

        // 過去契約も含めた最終施術取得
        $lastTreatment = $reservationService->getLastTreatmentWithOld($contractResult);
        $lastTreatmentHopeDate = $lastTreatment->hope_date ?? "";

        // 最終ペナルティ消化取得
        $lastCancelPenalty = $reservationModel->getLastCancelPenalty($contractResult->id);

        // 予約希望日がbaseDateの日付以降であることの確認
        $baseDate = $conditionCheckService->getBaseDate($contractResult, $courseResult, $lastCancelPenalty, $lastTreatmentHopeDate);
        if ($validatedData['hopeDate'] < $baseDate) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E9017", "hopeDate");
        }

        // 予約希望日がmaxDateの日付以前であることの確認
        $maxDate = $conditionCheckService->getMaxDate($contractResult, $courseResult, $baseDate, $lastTreatmentHopeDate, $lastCancelPenalty);
        if ($validatedData['hopeDate'] > $maxDate) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E9018", "hopeDate");
        }

        // トリートメント予約登録
        $reservationId = $reservationService->registTreatmentReserve($validatedData, $customerResult, $contractResult, $courseResult, $shopResult);

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'reservationId' => $reservationId
        ]);
    }

    // 予約変更
    public function put(Request $request, $reservationId, Customer $customerModel, Contract $contractModel, Course $courseModel, Shop $shopModel, Reservation $reservationModel, ReservationService $reservationService, ConditionCheckService $conditionCheckService)
    {
        // バリデーションチェック
        $meageRequest = array_merge($request->all(), ["reservationId" => $reservationId]);
        $validator = Validator::make($meageRequest, [
            'reservationId' => 'required | integer',
            'shopId' => 'required | integer | digits_between:1,2',
            'hopeDate' => 'required | string | date_format:Y-m-d',
            'hopeTimeCd' => 'required | integer'
        ], UtilService::getValidateMessage());

        $validator->sometimes('hopeTimeCd', ['regex:/^(1|3|5|7|9|11|12|13|15|17|19|){1}$/'], function ($input) {
            return $input->reservationType === 1;
        });

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

        // 予約情報取得
        $reservationResult = $reservationModel->getActiveReservation($reservationId);
        if (empty($reservationResult)) {
            // エラーレスポンス返却
            return response()->json([
                'apiStatus' => 400,
                'errorReasonCd' => "E5002",
                'errorKey' => 'reservationId'
            ]);
        }

        // ショップ情報取得
        $shopResult = $shopModel->getShopInfo($validatedData['shopId']);
        if (empty($shopResult)) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002", "shopId");
        }

        // カウンセリングの場合
        if ($reservationResult->type === 1) {
            // カウンセリング予約更新
            $reservationService->updateCounselingReserve($reservationId, $validatedData, $shopResult);

        } elseif ($reservationResult->type === 2) {
            // トリートメントの場合

            // 顧客情報取得
            $customerResult = $customerModel->getCustomerById($reservationResult->customer_id);
            if (empty($customerResult)) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E5002");
            }
            // 契約情報取得
            $contractResult = $contractModel->getActiveContract($reservationResult->contract_id);
            if (empty($contractResult)) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E5002");
            }
            // コース情報取得
            $courseResult = $courseModel->getCourse($contractResult->course_id);
            if (empty($courseResult) || $courseResult->treatment_type === 2) { // 整体は予約不可
                // エラーレスポンス返却
                throw new MyApiException(400, "E5002");
            }

            // hopeTimeCdバリデーションチェック
            if ($courseResult->weekdays_plan_type === 0) {
                if (!array_key_exists($validatedData['hopeTimeCd'], config('myConfig.tokutoku_90_room_time_list'))) {
                    // エラーレスポンス返却
                    throw new MyApiException(400, "E9003", "hopeTimeCd");
                }
            } elseif ($courseResult->length === 1) {
                if (!array_key_exists($validatedData['hopeTimeCd'], config('myConfig.30_room_time_list'))) {
                    // エラーレスポンス返却
                    throw new MyApiException(400, "E9003", "hopeTimeCd");
                }
            } elseif ($courseResult->length === 2) {
                if (!array_key_exists($validatedData['hopeTimeCd'], config('myConfig.60_room_time_list'))) {
                    // エラーレスポンス返却
                    throw new MyApiException(400, "E9003", "hopeTimeCd");
                }
            } elseif ($courseResult->length === 3) {
                if (!array_key_exists($validatedData['hopeTimeCd'], config('myConfig.90_room_time_list'))) {
                    // エラーレスポンス返却
                    throw new MyApiException(400, "E9003", "hopeTimeCd");
                }
            } else {
                // エラーレスポンス返却
                throw new MyApiException(400, "E5002", null);
            }

            // 契約期間(end_date)チェック
            if ($conditionCheckService->checkContractEndDate($contractResult, $courseResult, $validatedData['hopeDate']) === false) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E9016", "hopeDate");
            }

            // コンディションチェック
            // 不正コンディション一覧取得
            $failedConditions = $conditionCheckService->getFailedConditions($customerResult, $contractResult, $courseResult);
            if (!empty($failedConditions)) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E9014");
            }

            // 過去契約も含めた最終施術取得
            $lastTreatment = $reservationService->getLastTreatmentWithOld($contractResult);
            $lastTreatmentHopeDate = $lastTreatment->hope_date ?? "";

            // 最終ペナルティ消化取得
            $lastCancelPenalty = $reservationModel->getLastCancelPenalty($contractResult->id);

            // 予約希望日がbaseDateの日付以降であることの確認
            $baseDate = $conditionCheckService->getBaseDate($contractResult, $courseResult, $lastCancelPenalty, $lastTreatmentHopeDate);
            if ($validatedData['hopeDate'] < $baseDate) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E9017", "hopeDate");
            }

            // 予約希望日がmaxDateの日付以前であることの確認
            $maxDate = $conditionCheckService->getMaxDate($contractResult, $courseResult, $baseDate, $lastTreatmentHopeDate, $lastCancelPenalty);
            if ($validatedData['hopeDate'] > $maxDate) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E9018", "hopeDate");
            }

            // トリートメント予約更新
            $reservationService->updateTreatmentReserve($reservationId, $validatedData, $courseResult, $shopResult, $customerResult);

        } else {
            // カウンセリング、トリートメント以外の場合
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002", "reservationId");
        }


        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200
        ]);
    }

    // 予約キャンセル
    public function cancel($reservationId, Reservation $reservationModel, Customer $customerModel, Contract $contractModel, Course $courseModel, ReservationService $reservationService, ConditionCheckService $conditionCheckService)
    {
        // バリデーションチェック
        $validator = Validator::make(["reservationId" => $reservationId], [
            'reservationId' => 'required | integer '
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

        // 予約情報取得
        $reservationResult = $reservationModel->getActiveReservation($reservationId);
        if (empty($reservationResult)) {
            // エラーレスポンス返却
            return response()->json([
                'apiStatus' => 400,
                'errorReasonCd' => "E5002",
                'errorKey' => 'reservationId'
            ]);
        }

        // カウンセリングの場合
        if ($reservationResult->type === 1) {
            // キャンセル処理
            $reservationModel->cancel($reservationId);

        } elseif ($reservationResult->type === 2) {
            // トリートメントの場合

            // 契約情報取得
            $contractResult = $contractModel->getActiveContract($reservationResult->contract_id);
            if (empty($contractResult)) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E5002");
            }
            // 顧客情報取得
            $customerResult = $customerModel->getCustomerById($contractResult->customer_id);
            if (empty($customerResult)) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E5002");
            }
            // コース情報取得
            $courseResult = $courseModel->getCourse($reservationResult->course_id);
            if (empty($courseResult) || $courseResult->treatment_type === 2) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E5002");
            }

            // コンディションチェック
            // 不正コンディション一覧取得
            $failedConditions = $conditionCheckService->getFailedConditions($customerResult, $contractResult, $courseResult);
            if (!empty($failedConditions)) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E9014");
            }

            // 脱毛トリートメントの場合消化キャンセル処理を行う(パック:当日の場合、新月額:予約日、前日、2日前の場合)
            if ($courseResult->treatment_type === 0) {
                // パックの場合
                if ($courseResult->type === 0) {
                    if ($reservationResult->hope_date === date('Y-m-d')) {
                        // 最終消化時キャンセル禁止処理
//                         if ($courseResult->times !== 0 && ($contractResult->r_times + 1) >= $courseResult->times) {
//                             // エラーレスポンス返却
//                             throw new MyApiException(400, "E9019");
//                         }
                        // キャンセル消化処理
                        $reservationService->cancelPenaltyTreatment($reservationResult, $contractResult, $courseResult);
                    } else {
                        // 通常キャンセル
                        $reservationModel->cancel($reservationId);
                    }
                } else {
                    // 月額の場合
                    // 予約日まで2日以内の場合、消化処理を行う
                    if ((new \DateTime($reservationResult->hope_date))->diff(new \DateTime(date('Y-m-d')))->format('%a') <= 2) {
                        // キャンセル消化処理
                        $reservationService->cancelPenaltyTreatment($reservationResult, $contractResult, $courseResult);
                    } else {
                        // 通常キャンセル
                        $reservationModel->cancel($reservationId);
                    }
                }

            } elseif ($courseResult->treatment_type === 1)  {
                // エステの場合(当日の場合、消化処理を行う。脱毛パックと同じ)
                if ($reservationResult->hope_date === date('Y-m-d')) {
                    // 最終消化時キャンセル禁止処理
//                     if ($courseResult->times !== 0 && ($contractResult->r_times + 1) >= $courseResult->times) {
//                         // エラーレスポンス返却
//                         throw new MyApiException(400, "E9019");
//                     }
                    // キャンセル消化処理
                    $reservationService->cancelPenaltyTreatment($reservationResult, $contractResult, $courseResult);
                } else {
                    // 通常キャンセル
                    $reservationModel->cancel($reservationId);
                }
            } else { //脱毛の場合
                // エラーレスポンス返却
                throw new MyApiException(400, "E5002", "reservationId");
            }

        } else { // カウンセリング、トリートメント以外の場合
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002", "reservationId");
        }

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200
        ]);
    }

}
