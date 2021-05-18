<?php

namespace App\Http\Controllers\Api\Kireimo;

use App\Http\Controllers\Controller;
use App\Model\Kireimo\Customer;
use App\Model\Kireimo\NutritionQuestion;
use App\Service\UtilService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Model\Kireimo\NutritionAnswers;
use App\Exceptions\MyApiException;

class NutritionQuestionController extends Controller
{
    public function get(NutritionQuestion $nutritionQuestionModel) {
        $nutritionQuestionResult = $nutritionQuestionModel->getNutritionQuestion();

        if ($nutritionQuestionResult->count() < 1) {
            // レスポンス返却(0件)
            return response()->json([
                'apiStatus' => 204,
                'count' => 0
            ]);
        }

        // レスポンスBody生成
        $responseBody = [];
        foreach($nutritionQuestionResult as $key => $value) {
            $responseBody[$key]['id'] = $value->id;
            $responseBody[$key]['question'] = $value->question;
        }

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'count' => $nutritionQuestionResult->count(),
            'body' => $responseBody
        ]);
    }

    public function post(Request $request, $customerId, NutritionAnswers $nutritionAnswersModel, Customer $customerModel) {
        // バリデーションチェック
        $validator = Validator::make($request->all(), [
            'question01' => 'required | boolean',
            'question02' => 'required | boolean',
            'question03' => 'required | boolean',
            'question04' => 'required | boolean',
            'question05' => 'required | boolean',
            'question06' => 'required | boolean',
            'question07' => 'required | boolean',
            'question08' => 'required | boolean',
            'question09' => 'required | boolean',
            'question10' => 'required | boolean',
            'question11' => 'required | boolean',
            'question12' => 'required | boolean'
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

        \DB::beginTransaction();
        try {
            // 顧客存在確認
            $customerResult = $customerModel->getCustomerById((int)$customerId);
            if (empty($customerResult)) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E5002", "customerId");
            }

            $nutritionAnswersModel->customer_id = $customerId;
            $nutritionAnswersModel->question01 = $validatedData['question01'];
            $nutritionAnswersModel->question02 = $validatedData['question02'];
            $nutritionAnswersModel->question03 = $validatedData['question03'];
            $nutritionAnswersModel->question04 = $validatedData['question04'];
            $nutritionAnswersModel->question05 = $validatedData['question05'];
            $nutritionAnswersModel->question06 = $validatedData['question06'];
            $nutritionAnswersModel->question07 = $validatedData['question07'];
            $nutritionAnswersModel->question08 = $validatedData['question08'];
            $nutritionAnswersModel->question09 = $validatedData['question09'];
            $nutritionAnswersModel->question10 = $validatedData['question10'];
            $nutritionAnswersModel->question11 = $validatedData['question11'];
            $nutritionAnswersModel->question12 = $validatedData['question12'];
            // DB登録
            $nutritionAnswersModel->save();

            \DB::commit();
        } catch (\Exception $e){
            \DB::rollBack();

            // エラーレスポンス返却
            throw $e;
        }

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200
        ]);
    }

}