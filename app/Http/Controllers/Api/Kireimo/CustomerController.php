<?php

namespace App\Http\Controllers\Api\Kireimo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Model\Kireimo\Bank;
use App\Model\Kireimo\Customer;
use App\Model\Kireimo\GeneTypePatterns;
use App\Model\Kireimo\NutritionAnswers;
use App\Model\Kireimo\NutritionQuestion;
use App\Model\Kireimo\Shop;
use App\Model\Kireimo\Smartpit;
use App\Service\CustomerService;
use App\Service\UtilService;
use App\Rules\MyEmail;
use App\Rules\Kana;
use App\Rules\SpaceInvalid;
use App\Exceptions\MyApiException;
use App\Model\Kireimo\VirtualBank;

class CustomerController extends Controller
{
    // 顧客情報取得
    public function get($customerId, Customer $customerModel, Bank $bankModel, Smartpit $smartpitModel, VirtualBank $virtualBankModel
        , GeneTypePatterns $geneTypePatternModel, NutritionQuestion $nutritionQuestionModel, NutritionAnswers $nutritionAnswersModel) {

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
        $customerResult = $customerModel->getCustomerById((int)$customerId);
        if (empty($customerResult)) {
            // レスポンス返却(0件)
            return response()->json([
                'apiStatus' => 204,
                'errorReasonCd' => null,
                'errorKey' => null
            ]);
        }


        // 返金口座情報取得
        $bankResult = $bankModel->getBank($customerResult->id);
        $bank = null;
        if (!empty($bankResult)) {
            $bank['bankName'] = $bankResult->bank_name;
            $bank['bankBranch'] = $bankResult->bank_branch;
            $bank['bankAccountType'] = $bankResult->bank_account_type;
            $bank['bankAccountNo'] = $bankResult->bank_account_no;
            $bank['bankAccountName'] = $bankResult->bank_account_name;
        }

        // smartpit番号取得
        if (!empty($customerResult->smartpit_id)) {
            $smartpitResult = $smartpitModel->getSmartpit($customerResult->smartpit_id);
        }

        // バーチャル口座情報取得
        $virtualBankResult = $virtualBankModel->getVirtualBank($customerResult->id);
        $virtualBank = null;
        if (!empty($virtualBankResult)) {
            $virtualBank['branchName'] = $virtualBankResult->branch_name;
            $virtualBank['branchNo'] = $virtualBankResult->branch_no;
            $virtualBank['virtualNo'] = $virtualBankResult->virtual_no;
        }

        // 遺伝子関連情報取得
        $geneInfo = null;
        if (!empty($customerResult->sugar_risk_id) && !empty($customerResult->protein_risk_id) && !empty($customerResult->fat_risk_id)) {
            $geneTypePatternResult = $geneTypePatternModel->getGeneInfo($customerResult->sugar_risk_id, $customerResult->protein_risk_id, $customerResult->fat_risk_id);
            if (!empty($geneTypePatternResult)) {
                $geneInfo['sugarRiskId'] = $customerResult->sugar_risk_id; // 糖質リスクタイプ 0.未登録、1.○、2.△、3.×
                $geneInfo['proteinRiskId'] = $customerResult->protein_risk_id; // たんぱく質リスクタイプ 0.未登録、1.○、2.△、3.×
                $geneInfo['fatRiskId'] = $customerResult->fat_risk_id; // 脂質リスクタイプ 0.未登録、1.○、2.△、3.×
                $geneInfo['geneTypeId'] = $geneTypePatternResult->gene_type_id; // 遺伝子タイプID
                $geneInfo['obesityRiskId'] = $geneTypePatternResult->obesity_risk_id; // 肥満リスクID
                $geneInfo['obesityRiskLevel'] = $geneTypePatternResult->obesity_risk_level; // 肥満リスクレベル
                $geneInfo['intakeRestrictionId'] = $geneTypePatternResult->intake_restriction_id; // 摂取制限ID
                $geneInfo['obesityPatternId'] = $geneTypePatternResult->obesity_pattern_id; // 肥満パターンID
                $geneInfo['obesityRiskEvaluationId'] = $geneTypePatternResult->gene_type_evaluation_id; // 遺伝子別評価ID
                $geneInfo['mainSupplementId'] = $geneTypePatternResult->main_supplement_id; // メインサプリメントID
                $geneInfo['subSupplementId'] = $geneTypePatternResult->sub_supplement_id; // サブサプリメントID
                $geneInfo['mainTreatmentEquipmentId'] = $geneTypePatternResult->main_treatment_equipment_id; // メイン機械ID
                $geneInfo['subTreatmentEquipmentId'] = $geneTypePatternResult->sub_treatment_equipment_id; // サブ機械ID
                $geneInfo['recommendedExercise'] = $geneTypePatternResult->recommended_exercise; // 運動
            }
        }


        // アンケート回答取得
        $nutritionAnswers = $nutritionAnswersModel->getNutritionAnswers($customerId);
        // アンケート質問テーブル情報取得
        $nutritionQuestion = $nutritionQuestionModel->getNutritionQuestion();
        // 欠如栄養情報取得
        $nutritionLackInfo = null;

        if (!empty($nutritionAnswers)) {
            $nutritionLackInfo['vitaminB1Lack'] = false;
            $nutritionLackInfo['vitaminB2Lack'] = false;
            $nutritionLackInfo['vitaminB6Lack'] =  false;
            $nutritionLackInfo['pantothenicAcidLack'] = false;
            $nutritionLackInfo['lCarnitineLack'] = false;
            $nutritionLackInfo['niacinLack'] = false;

            foreach ($nutritionQuestion as $q) {
                $j = sprintf('%02d', $q['id']);
                if ($nutritionAnswers["question{$j}"] == 1) {
                    if ($q['vitaminB1_lack'] == 1) $nutritionLackInfo['vitaminB1Lack'] = true;
                    if ($q['vitaminB2_lack'] == 1) $nutritionLackInfo['vitaminB2Lack'] = true;
                    if ($q['vitaminB6_lack'] == 1) $nutritionLackInfo['vitaminB6Lack'] =  true;
                    if ($q['pantothenic_acid_lack'] == 1) $nutritionLackInfo['pantothenicAcidLack'] = true;
                    if ($q['l_carnitine_lack'] == 1) $nutritionLackInfo['lCarnitineLack'] = true;
                    if ($q['niacin_lack'] == 1) $nutritionLackInfo['niacinLack'] = true;
                }
            }
        }


        // レスポンスBody生成
        $responseBody = [];
        $responseBody['customerNo'] = $customerResult->no;
        $responseBody['ctype'] = $customerResult->ctype;
        $responseBody['name1'] = explode('　', trim($customerResult->name), 2)[0] ?? null;
        $responseBody['name2'] = explode('　', trim($customerResult->name), 2)[1] ?? null;
        $responseBody['nameKana1'] = explode('　', trim($customerResult->name_kana), 2)[0] ?? null;
        $responseBody['nameKana2'] = explode('　', trim($customerResult->name_kana), 2)[1] ?? null;
        $responseBody['birthday'] = $customerResult->birthday;
        $responseBody['tel'] = $customerResult->tel;
        $responseBody['sex'] = (int)$customerResult->sex;
        $responseBody['mail'] = $customerResult->mail;
        $responseBody['zip'] = $customerResult->zip;
        $responseBody['prefCd'] = $customerResult->pref;
        $responseBody['address'] = $customerResult->address;
        $responseBody['pairNameKana1'] = explode('　', trim($customerResult->pair_name_kana), 2)[0] ?? null;
        $responseBody['pairNameKana2'] = explode('　', trim($customerResult->pair_name_kana), 2)[1] ?? null;
        $responseBody['hopesDiscountFlg'] = $customerResult->hopes_discount === 1 ? true : false;
        $responseBody['hopeCampaign'] = $customerResult->hope_campaign;
        $responseBody['adcodeId'] = $customerResult->adcode;
        $responseBody['bank'] = $bank;
        $responseBody['smartpitNo'] = $smartpitResult->smartpit_no ?? null;
        $responseBody['virtualBank'] = $virtualBank;
        $responseBody['geneInfo'] = $geneInfo;
        $responseBody['nutritionLackInfo'] = $nutritionLackInfo;

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'body' => $responseBody
        ]);
    }


    // 顧客情報登録
    public function post(Request $request, Shop $shopModel, CustomerService $customerService)
    {
        // バリデーションチェック
        $validator = Validator::make($request->all(), [
            'shopId' => 'required | integer | digits_between:1,2',
            'inviteCustomerNo' => 'string',
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
        $shopResult = $shopModel->getShopInfo($validatedData['shopId']);
        if (empty($shopResult)) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002", "shopId");
        }

        // 顧客登録
        $customer = $customerService->register($request->get("customer"), $shopResult, $validatedData['inviteCustomerNo'] ?? null);

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'customerId' => $customer->id
        ]);
    }

    // 顧客情報更新
    public function patch(Request $request, $customerId, Customer $customerModel, Bank $bankModel)
    {
        // バリデーションチェック
        $meageRequest = array_merge($request->all(), ["id" => $customerId]);
        $validator = Validator::make($meageRequest, [
            'id' => 'required | string',
            'name1' => ['filled', 'string', 'max:25', new SpaceInvalid, 'required_with:"name2"'],
            'name2' => ['filled', 'string', 'max:25', new SpaceInvalid, 'required_with:"name1"'],
            'nameKana1' => ['filled', 'string', 'max:25', new SpaceInvalid, new Kana, 'required_with:"nameKana2"'],
            'nameKana2' => ['filled', 'string', 'max:25', new SpaceInvalid, new Kana, 'required_with:"nameKana1"'],
            'birthday' => ['filled', 'string', 'date_format:Y-m-d', 'regex:/^(?!0000).*$/'],
            'tel' => ['filled', 'between:12,13', 'regex:/^0\d+-\d+-\d+$/'],
            'mail' => ['filled', 'string', 'max:63', new MyEmail],
            'zip' => ['filled', 'string', 'size:8', 'regex:/^[0-9]+[-][0-9]+$/'],
            'prefCd' => 'filled | integer | digits_between:1,2',
            'address' => 'filled | string | max:60',
            'bank' => 'array',
            'bank.bankName' => ['required_with:bank', 'string', 'max:50'],
            'bank.bankBranch' => ['required_with:bank', 'string', 'max:50'],
            'bank.bankAccountType' => ['required_with:bank', 'integer', 'min:1', 'max:2'],
            'bank.bankAccountNo' => ['required_with:bank', 'string', 'max:7'],
            'bank.bankAccountName' => ['required_with:bank', 'string', 'max:50'],
            'lineMid' => ['string', 'size:33', 'regex:/^[0-9a-zA-Z]*$/']
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
            // 顧客更新
            if (array_key_exists('name1', $validatedData)) $customerModel->name = $validatedData['name1'] . "　" . $validatedData['name2'];
            if (array_key_exists('nameKana1', $validatedData)) $customerModel->name_kana = $validatedData['nameKana1'] . "　" . $validatedData['nameKana2'];
            if (array_key_exists('birthday', $validatedData)) $customerModel->birthday = $validatedData['birthday'];
            if (array_key_exists('tel', $validatedData)) $customerModel->tel = $validatedData['tel'];
            if (array_key_exists('mail', $validatedData)) $customerModel->mail = $validatedData['mail'];
            if (array_key_exists('zip', $validatedData)) $customerModel->zip = $validatedData['zip'];
            if (array_key_exists('prefCd', $validatedData)) $customerModel->pref = $validatedData['prefCd'];
            if (array_key_exists('address', $validatedData)) $customerModel->address = $validatedData['address'];
            if (array_key_exists('pairNameKana1', $validatedData)) $customerModel->pair_name_kana = $validatedData['pairNameKana1'] . "　" . $validatedData['pairNameKana2'];
            if (array_key_exists('hopesDiscountFlg', $validatedData)) $customerModel->hopes_discount = $validatedData['hopesDiscountFlg'];
            if (array_key_exists('hopeCampaign', $validatedData)) $customerModel->hope_campaign = $validatedData['hopeCampaign'];

            if (array_key_exists('bank', $validatedData) && !empty($validatedData['bank'])) {
                $bankModel->bank_name = $validatedData['bank']['bankName'];
                $bankModel->bank_branch = $validatedData['bank']['bankBranch'];
                $bankModel->bank_account_type = $validatedData['bank']['bankAccountType'];
                $bankModel->bank_account_no = $validatedData['bank']['bankAccountNo'];
                $bankModel->bank_account_name = $validatedData['bank']['bankAccountName'];

                Bank::updateOrCreate(['customer_id' => $customerId, 'del_flg' => 0], $bankModel->getAttributes());
            }

            // lineMidが既に存在していた場合、既存側をnullに更新する
            if (array_key_exists('lineMid', $validatedData) && !empty($validatedData['lineMid'])) {
                $customerModel
                ->where('line_mid', $validatedData['lineMid'])
                ->where('del_flg', 0)
                ->update(['line_mid' => NULL]);
            }

            if (array_key_exists('lineMid', $validatedData)) $customerModel->line_mid = $validatedData['lineMid'];

            $resultCount = $customerModel
            ->where('id', $customerId)
            ->where('del_flg', 0)
            ->update($customerModel->getAttributes());

            if ($resultCount == 0) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E5002");
            }

            // データ重複チェック
            // 同一顧客検索(同一人物チェック)
            $updatedCustomerDate = $customerModel->getCustomerById($customerId);
            $result = $customerModel->selectSamePerson($updatedCustomerDate->mail, $updatedCustomerDate->tel, $updatedCustomerDate->birthday, $updatedCustomerDate->name, $updatedCustomerDate->name_kana);

            if ($result->count() >= 2) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E9010", null, "同一人物チェックエラー");
            }

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
