<?php

namespace App\Http\Controllers\Api\Kireimo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Model\Kireimo\Contract;
use App\Model\Kireimo\Course;
use App\Model\Kireimo\Shop;
use App\Service\ReservationService;
use App\Service\UtilService;
use App\Exceptions\MyApiException;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class ReservableCheckController extends Controller
{
    // カウンセリング予約空き状況確認
    public function counseling(Request $request, Shop $shopModel, Contract $contractModel, ReservationService $reservationService)
    {
        // バリデーションチェック
        $validator = Validator::make($request->all(), [
            'shopId' => 'required | integer',
            'date' => 'required | string | date_format:Y-m-d',
            'dateUntil' => 'string|after_or_equal:date|date_format:Y-m-d',
        ], UtilService::getValidateMessage());

        $validator->sometimes('contractId', 'required|integer', function ($input) {
            return $input->reservationType === "2";
        });

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
        $shopResult = $shopModel->getShopInfo($validatedData['shopId']);

        if (empty($shopResult)) {
            // エラーレスポンス返却
            return response()->json([
                'apiStatus' => 400,
                'errorReasonCd' => "E5002",
                'errorKey' => 'shopId'
            ]);
        }

        // カウンセリング予約空き確認
        if (!empty($validatedData['dateUntil'])) {
            $responseBody = [];

            $dateFrom = Carbon::parse($validatedData['date']);
            $dateUntil = Carbon::parse($validatedData['dateUntil']);

            // 35日分以上は取得不可
            if ($dateUntil->lte(CarbonImmutable::parse($validatedData['date'])->addDays(34)) === false) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E9003", "dateUntil");
            }

            for ( ; $dateFrom->lte($dateUntil) === true ;) {
                $result = $reservationService->checkReservable($shopResult, $dateFrom->format('Y-m-d'));

                $ReservableResult = [];
                foreach ($result as $key => $value) {
                    $ReservableResult[$key] = array ("reservableRooms" => $value['emptySize'], "maxRooms" => $value['maxSize']);
                }
                $responseBody[$dateFrom->format('Y-m-d')] = $ReservableResult;
                $dateFrom->addDays(1);
            }

        } else {
            $result = $reservationService->checkReservable($shopResult, $validatedData['date']);
            $responseBody = [];
            foreach ($result as $key => $value) {
                $responseBody[$key] = array ("reservableRooms" => $value['emptySize'], "maxRooms" => $value['maxSize']);
            }
        }


        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'body' => $responseBody
        ]);

    }

    // トリートメント予約空き状況確認
    public function treatment(Request $request, Shop $shopModel, Contract $contractModel, ReservationService $reservationService, Course $courseModel)
    {
        // バリデーションチェック
        $validator = Validator::make($request->all(), [
            'shopId' => 'required | integer',
            'date' => 'required | string | date_format:Y-m-d',
            'contractId' => 'required | integer',
            'selfReservationID' => 'filled | integer',
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

        // コース情報取得
        $courseResult = $courseModel->getCourse($contractResult->course_id);
        if (empty($courseResult)) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002");
        }

        // トリートメント予約空き確認
        $result = $reservationService->checkTreatmentReservable($validatedData['date'], $courseResult, $shopResult, $contractResult->customer_id, $validatedData['selfReservationID'] ?? null);
        $responseBody = [];
        foreach ($result as $key => $value) {
            $responseBody[$key] = array ("reservableRooms" => $value['emptySize'], "maxRooms" => $value['maxSize']);
        }

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'body' => $responseBody
        ]);

    }
}
