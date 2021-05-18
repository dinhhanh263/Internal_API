<?php

namespace App\Http\Controllers\Api\Kireimo;

use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Model\Kireimo\Contract;
use App\Service\UtilService;

class ContractController extends Controller
{
    // 顧客契約一覧取得
    public function index($customerId, Contract $contractModel) {
        // バリデーションチェック
        $validator = Validator::make(["customerId" => $customerId], [
            'customerId' => 'required | integer '
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
        $contractCollection = $contractModel->getContractList((int)$customerId);
        if ($contractCollection->count() < 1) {
            // レスポンス返却(0件)
            return response()->json([
                'apiStatus' => 204,
                'count' => 0
            ]);
        }

        // レスポンスBody生成
        $responseBody = [];
        foreach($contractCollection as $key => $value) {
            $responseBody[$key]['contractId'] = $value->id;
            $responseBody[$key]['status'] = $value->status;
            $responseBody[$key]['oldContractId'] = $value->old_contract_id;
            $responseBody[$key]['courseId'] = $value->course_id;
            $responseBody[$key]['contractDate'] = $value->contract_date;
            $responseBody[$key]['latestDate'] = $value->latest_date;
            $responseBody[$key]['endDate'] = $value->end_date;
            $responseBody[$key]['times'] = $value->times;
            $responseBody[$key]['rTimes'] = $value->r_times;
            $responseBody[$key]['startYm'] = $value->start_ym;
            $responseBody[$key]['extensionEndDate'] = $value->extension_end_date;
            $responseBody[$key]['courseTreatmentType'] = $value->course_treatment_type;
            $responseBody[$key]['courseType'] = $value->course_type;
            $responseBody[$key]['courseName'] = $value->course_name;
            $responseBody[$key]['courseZeroFlg'] = $value->course_zero_flg === 1 ? true : false;
            $responseBody[$key]['courseNewFlg'] = $value->course_new_flg === 1 ? true : false;
            $responseBody[$key]['courseIntervalDate'] = $value->course_interval_date;
            $responseBody[$key]['courseSalesStartDate'] = $value->course_sales_start_date;
            $responseBody[$key]['courseSalesEndDate'] = $value->course_sales_end_date;
            $responseBody[$key]['courseReservationMaxTimes'] = $value->course_reservation_max_times;
            $responseBody[$key]['courseMinorPlanFlg'] = $value->course_minor_plan_flg === 1 ? true : false;
        }

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'count' => $contractCollection->count(),
            'body' => $responseBody
        ]);
    }

}
