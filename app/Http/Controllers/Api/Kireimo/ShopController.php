<?php

namespace App\Http\Controllers\Api\Kireimo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Model\Kireimo\Shop;
use App\Service\UtilService;

class ShopController extends Controller
{
    public function index(Request $request, Shop $shopModel)
    {
        // バリデーションチェック
        $validator = Validator::make($request->all(), [
            'type' => 'required | string | in:"0","1","2"',
            'targetDate' => 'string | date_format:Y-m-d'
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

        // DB検索
        if ($validator->getData()['type'] === "0") { // 全て
            $result_collection = $shopModel->getALLReservableShop();
        } else if ($validator->getData()['type'] === "1") { // カウンセリング
            $result_collection = $shopModel->getCounselingReservableShop($validatedData['targetDate'] ?? null);
        } else if ($validator->getData()['type'] === "2") { // トリートメント
            $result_collection = $shopModel->getTreatmentReservableShop($validatedData['targetDate'] ?? null);
        }

        if ($result_collection->count() < 1) {
            // レスポンス返却(0件)
            return response()->json([
                'apiStatus' => 204,
                'count' => 0
            ]);
        }

        // レスポンスBody生成
        $responseBody = [];
        foreach($result_collection as $key => $value) {
            $responseBody[$key]['shopId'] = $value->id;
            $responseBody[$key]['pref'] = $value->pref;
            $responseBody[$key]['prefName'] = $value->prefectures_name;
            $responseBody[$key]['name'] = $value->name;
            $responseBody[$key]['openDate'] = $value->open_date;
            $responseBody[$key]['counselingStartDate'] = $value->rsv_date;
            $responseBody[$key]['treatmentStartDate'] = $value->rsv_date_treatment;
            $responseBody[$key]['closeDate'] = $value->close_date;
            $responseBody[$key]['area'] = $value->area;
            $responseBody[$key]['detailArea'] = $value->detail_area;
            $responseBody[$key]['detailAreaName'] = $value->detail_area_name;
            $responseBody[$key]['address'] = $value->address;
            $responseBody[$key]['access'] = $value->access;
            $responseBody[$key]['url'] = $value->url;
            $responseBody[$key]['latitude'] = $value->latitude;
            $responseBody[$key]['longitude'] = $value->longitude;
            $responseBody[$key]['chiropracticFlg'] = $value->chiropractic_flg === 1 ? true : false;
            $responseBody[$key]['estheticsFlg'] = $value->esthetics_flg === 1 ? true : false;
        }

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'count' => $result_collection->count(),
            'body' => $responseBody
        ]);

    }
}
