<?php

namespace App\Service;

use App\Model\Kireimo\Customer;
use GuzzleHttp\Psr7\Response;

class SmsService
{
    public function __construct()
    {
        $this->customerModel = new Customer();
    }

    /**
     * paramバリデーションチェック
     *
     * @param array $requestParams リクエストのparam
     * @param int $sendParamType パラメータータイプ(1:電話番号、2:顧客ID)
     * @param int $mailStatus メールステータス(0:送信可のみ、1:全て)
     * @param string $templateText テンプレートテキスト
     * @return array [$sendBody,$failBody] 送信対象一覧、エラー対象一覧
     */
    public function paramValid($requestParams, $sendParamType, $mailStatus, $templateText) {
        $sendBody = [];
        $failBody = [];

        // リクエスト重複チェック&重複行削除
        if ($sendParamType === 1) {//電話番号
            $duplicationValue = $this->getDuplicationTel($requestParams);
        } elseif ($sendParamType === 2) {//会員No
            $duplicationValue = $this->getDuplicationCustomerNo($requestParams);
        }
        foreach ($duplicationValue as $key => $diffValue) {
            $failBody[$key] = ['failCustomerKey' => $diffValue, 'faliReason' => 'E9005'];
            unset($requestParams[$key]);
        }

        // 個別宛先別バリデーションチェック
        foreach ($requestParams as $key => $param) {
            // 差し込み文字パラメーター整合性チェック
            for ($i=1; $i <= 5; $i++) {
                if (mb_strpos($templateText, "{{insertion_item{$i}}}", null, "UTF-8") !== FALSE) {
                    if (empty($param["insert{$i}"])) {
                        $failBody[$key] = ['failCustomerKey' => $sendParamType === 1 ? $param['toTel'] : $param['customerNo'], 'faliReason' => 'E9009'];
                        continue 2; // 次の行のチェックへ
                    }
                }
            }

            if ($sendParamType === 1) {//電話番号
                $smsParamValidResult = $this->paramValidTypeTel($param['toTel']);
                if ($smsParamValidResult !=="") {
                    $failBody[$key] = $smsParamValidResult;
                } else {
                    // 送信先に追加
                    $sendBody[$key] = ['phone_number' => $param['toTel']];
                }
            } elseif ($sendParamType === 2) {//会員No
                // 会員番号から電話番号検索
                $customerResult = $this->customerModel->getCustomerByNo($param['customerNo']);
                // DBデータ存在チェック
                if (empty($customerResult)) {
                    // エラー(DBデータ不正)
                    $failBody[$key] = ['failCustomerKey' => $param['customerNo'], 'faliReason' => 'E5002'];
                    continue;
                }

                // 個別バリデーションチェック
                $smsParamValidResult = $this->paramValidTypeCustomerNo($customerResult, $mailStatus);

                if ($smsParamValidResult !=="") {
                    $failBody[$key] = $smsParamValidResult;
                } else {
                    // 送信先に追加
                    $sendBody[$key] = ['phone_number' => str_replace("-", "", $customerResult['tel'])];
                }
            }
        }

        ksort($failBody); // ソートする
        return [$sendBody, $failBody];
    }

    /**
     * param個別バリデーションチェック(電話番号タイプ)
     *
     * @param string $tel 電話番号
     * @return string バリデーション結果
     */
    private function paramValidTypeTel(string $tel) {

        // 電話番号形式チェック
        if (UtilService::checkTelFormat($tel) === false)  {
            return ['failCustomerKey' => $tel, 'faliReason' => 'E9002'];
        }

        return "";
    }

    /**
     * param個別バリデーションチェック(顧客Noタイプ)
     *
     * @param Customer $customer 顧客情報
     * @param int $mailStatus リクエストメールステータス
     * @return string バリデーション結果
     */
    private function paramValidTypeCustomerNo($customer, $mailStatus) {

        // 顧客DB電話番号からハイフン除去
        $tel = str_replace("-", "", $customer['tel']);
        // 電話番号形式チェック
        if (UtilService::checkTelFormat($tel) === false)  {
            return ['failCustomerKey' => $customer['no'], 'faliReason' => 'E9002'];
        }

        // 電話番号に紐づくユーザーのcustomer.mail_status取得
        $resultCollection = $this->customerModel->getCustomerMailStatusByTel($tel);

        // DBデータエラー(対象データが存在しない)
        if ($resultCollection->count() < 1) {
            return ['failCustomerKey' => $customer['no'], 'faliReason' => 'E5002'];
        }
        // DBデータエラー(データ重複)
        if ($resultCollection->count() >= 2) {
            return ['failCustomerKey' => $customer['no'], 'faliReason' => 'E5003'];
        }

        // メールステータスチェック(リクエストmailStatus(0:送信可のみ、1:全て))
        if ($mailStatus === 0) {
            if ($resultCollection[0]->mail_status !== 0) {
                // エラー(customer.mail_statusが0でない場合)
                return ['failCustomerKey' => $customer['no'], 'faliReason' => 'E9007'];
            }
        }

        return "";
    }

    /**
     * 電話番号重複抽出
     *
     * @param array $requestParams リクエストのparam
     * @return array 重複している2つ目以降の配列
     */
    private function getDuplicationTel(array $requestParams) {
        $values = array_map(function($a){return $a['toTel'];}, $requestParams);
        $uniqueValues = array_unique($values);
        return array_diff_key($values, $uniqueValues);
    }

    /**
     * 会員No重複抽出
     *
     * @param array $requestParams リクエストのparam
     * @return array 重複している2つ目以降の配列
     */
    private function getDuplicationCustomerNo(array $requestParams) {
        $values = array_map(function($a){return $a['customerNo'];}, $requestParams);
        $uniqueValues = array_unique($values);
        return array_diff_key($values, $uniqueValues);
    }

    /**
     * エージェントにSMSリクエスト送信
     *
     * @param string $templateName テンプレート名
     * @param string $template テンプレート文章
     * @param string $url url
     * @param array $sendBody 送信対象
     * @param array $requestParams リクエストparam
     * @param string $requestId リクエストID
     * @param string $groupCd 組織コード
     * @param string $email ログインユーザーメールアドレス
     * @return Response エージェントからのレスポンス
     */
    public function sendPostRequest($templateName, $template, $url , $sendBody, $requestParams, $requestId, $groupCd, $email) {

        // 差し込み文字設定
        foreach ($sendBody as $key => $value) {
            for ($i=1; $i <= config('myConfig.sms_insert_count'); $i++) {
                if (!empty($requestParams[$key]["insert{$i}"])) $sendBody[$key]["insertion_item{$i}"] = $requestParams[$key]["insert{$i}"];
            }
        }

        // urlを本文に結合(半角スペース付与)
        if  (!empty($url)) {
            $template = $template ." ".$url;
        }

        // 結果通知メール設定(.envの定義+ユーザーに紐づくemail)
        $notificaionEmail = config('myConfig.sms_notificaion_email_array');
        if (!empty($email)) {$notificaionEmail[] = $email;}

        // リクエストパラメーター構築
        $jsonArray = null;
        $jsonArray = [
            "delivery_name"=>$templateName,
            "text_message"=>$template,
            "click_count"=>true,
            "notification_emails"=>$notificaionEmail,
            "bill_split_code"=>$groupCd,
            "contacts"=>array_values($sendBody) // 連番で無いとJSON変換時オブジェクトになるためキーを再設定
        ];
        $sendJson = json_encode($jsonArray, JSON_UNESCAPED_UNICODE);
        $headers = [
            "Content-Type" => "application/json",
            "token" => config('myConfig.smslink_token'),
            "Accept" => "application/json"
        ];

        // エージェントへのリクエストをログ出力
        \Log::channel('smslogs')->info("[SMS配信登録リクエスト]requestId:".$requestId.", url:".config('myConfig.smslink_url').'delivery'. ", Body:".$sendJson);

        $client = new \GuzzleHttp\Client();
        $agentResponse = $client->request(
            'POST',
            config('myConfig.smslink_url').'delivery', // Agentのベースurlと連結しurl構築
            ['body' => $sendJson,'headers' => $headers, 'http_errors' => false]);

        // エージェントからのレスポンスをログ出力
        \Log::channel('smslogs')->info("[SMS配信登録レスポンス]requestId:".$requestId.", Body:".$agentResponse->getBody());

        return $agentResponse;
    }

}