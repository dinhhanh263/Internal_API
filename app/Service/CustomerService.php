<?php

namespace App\Service;

use App\Model\Kireimo\Adcode;
use App\Model\Kireimo\Customer;
use App\Model\Kireimo\Introducer;
use App\Model\Kireimo\Ngword;
use App\Model\Kireimo\Shop;
use App\Exceptions\MyApiException;

class CustomerService
{
    private $customerModel;
    private $shopModel;
    private $ngwordModel;
    private $introducer;
    private $adcode;

    public function __construct(Customer $customerModel, Shop $shopModel, Ngword $ngwordModel, Introducer $introducer, Adcode $adcode)
    {
        $this->customerModel = $customerModel;
        $this->shopModel = $shopModel;
        $this->ngwordModel = $ngwordModel;
        $this->introducer = $introducer;
        $this->adcode = $adcode;
    }

    /**
     * 顧客登録
     *
     * @param Object $customer
     * @param Shop $shopObject
     * @param string $inviteCustomerNo
     * @param string $adcode
     *
     * @return Customer
     */
    public function register($customer, Shop $shopObject, string $inviteCustomerNo = null, string $adcode = null) {

        // NGワードチェック
        $ngWordCollenction = $this->ngwordModel->getAll();
        $targetList = array($customer['name1']."　".$customer['name2'], $customer['nameKana1']."　".$customer['nameKana2'], $customer['mail'], $customer['tel']);
        foreach ($ngWordCollenction ?? array() as $value) {
            $searchResult = array_search($value['name'], $targetList, true);
            if ($searchResult !== false) {
                if ($searchResult === 0) $key = "name1";
                if ($searchResult === 1) $key = "nameKana1";
                if ($searchResult === 2) $key = "mail";
                if ($searchResult === 3) $key = "tel";

                // エラーレスポンス返却
                throw new MyApiException(400, "E9003", $key);
            }
        }

        // 年齢チェック(15歳以上であること)
        if (!UtilService::checkAge($customer['birthday'])) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E9003", "birthday");
        }

        \DB::beginTransaction();
        try {
            // 顧客情報登録
            $this->customerModel->shop_id = $shopObject->id;
            $this->customerModel->name = $customer['name1'] . "　". $customer['name2'];
            $this->customerModel->name_kana = $customer['nameKana1']. "　". $customer['nameKana2'];
            $this->customerModel->birthday = $customer['birthday'];
            $this->customerModel->tel = $customer['tel'];
            $this->customerModel->mail = $customer['mail'];
            if (array_key_exists('pairNameKana1', $customer) && array_key_exists('pairNameKana2', $customer)) {
                if ($customer['pairNameKana1'] !== "") {
                    $this->customerModel->pair_name_kana = $customer['pairNameKana1']. "　". $customer['pairNameKana2'];
                } else {
                    $this->customerModel->pair_name_kana = "";
                }
            }
            if (array_key_exists('hopeCampaign', $customer)) $this->customerModel->hope_campaign = $customer['hopeCampaign'];
            if (array_key_exists('hopesDiscountFlg', $customer)) $this->customerModel->hopes_discount = $customer['hopesDiscountFlg'];


            // 同一顧客検索(同一人物チェック)
            $result = $this->customerModel->selectSamePerson($customer['mail'], $customer['tel'], $customer['birthday'], ($customer['name1']. "　" . $customer['name2']),($customer['nameKana1']. "　" . $customer['nameKana2']));

            if ($result->count() >= 2) {
                \Log::channel('errorlogs')->error("同一人物チェック:重複2件以上エラー");
                throw new MyApiException(500, "E5003", null, "同一人物チェックエラー");

            } elseif ($result->count() === 1) { // 同一人物あり
                $this->customerModel->rebook_flg = 1;
                $this->customerModel->id = $result[0]->id;

                // 更新処理
                $this->customerModel->where('id', $result[0]->id)->update($this->customerModel->getAttributes());

                $rebookFlg = true;

            } else { // 同一人物なし
                // 初期パスワード生成
                $this->customerModel->password = substr(hash('sha256', random_bytes(10)), 0, 8);
                // adcode設定
                if ($adcode !== null && $inviteCustomerNo === null) {
                    $adcodeObject = $this->adcode->getAdcodeId($adcode);
                    if (empty($adcodeObject)) {
                        $this->customerModel->adcode = "-";
                    } else {
                        $this->customerModel->adcode = $adcodeObject['id'];
                    }
                }

                // 顧客登録
                $this->customerModel->save();

                // 顧客レコード更新(顧客番号(shopコード + customer.id) 設定)
                $this->customerModel->where('id', $this->customerModel->id)->update(['no' => $shopObject->code . $this->customerModel->id]);
            }

            // 友達紹介処理
            if ($inviteCustomerNo !== null) {
                $introducer = $this->customerModel->getCustomerByNo($inviteCustomerNo);

                // introducerテーブル存在チェック
                if ($this->introducer->isExist($this->customerModel->id, $introducer->id ?? 0) === false) {
                    // introducerインサート
                    $this->introducer->customer_id =  $introducer->id ?? 0;
                    $this->introducer->introducer_customer_id = $this->customerModel->id;
                    $this->introducer->reg_date = date('Y-m-d H:i:s');
                    $this->introducer->edit_date = date('Y-m-d H:i:s');
                    $this->introducer->del_flg = 0;
                    $this->introducer->save();

                    if (empty($rebookFlg)) {
                        // 友達紹介用広告コード設定
                        $this->customerModel->adcode = config('myConfig.introduction_adcode');
                        // 顧客レコード更新
                        $this->customerModel->where('id', $this->customerModel->id)->update(['adcode' => config('myConfig.introduction_adcode')]);
                    }
                }
            }

            \DB::commit();

            return $this->customerModel;

        } catch (\Exception $e){
            \DB::rollBack();

            // エラーレスポンス返却
            throw $e;
        }
    }

}