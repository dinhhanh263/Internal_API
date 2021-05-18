<?php

namespace App\Service;

use App\Model\Kireimo\Customer;
use Illuminate\Support\Facades\Mail;
use Exception;
use App\Exceptions\MyApiException;

class MailService
{
    public function __construct()
    {
        $this->customerModel = new Customer();
    }

    /**
     * paramバリデーションチェック
     *
     * @param array $requestParams リクエストのparam
     * @param int $sendParamType パラメータータイプ(1:メールアドレス、2:顧客ID)
     * @param int $mailStatus メールステータス(0:送信可のみ、1:全て)
     * @param string $templateText テンプレートテキスト
     * @return array [$sendBody,$failBody] 送信対象一覧、エラー対象一覧
     */
    public function paramValid($requestParams, $sendParamType, $mailStatus, $templateText) {
        $sendBody = [];
        $failBody = [];

        // リクエスト重複チェック&重複行削除
        if ($sendParamType === 1) {//メールアドレス
            $duplicationValue = $this->getDuplicationMailAddress($requestParams);
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
            for ($i=1; $i <= config('myConfig.mail_insert_count'); $i++) {
                if (mb_strpos($templateText, "{{insertion_item{$i}}}", null, "UTF-8") !== FALSE) {
                    if (empty($param["insert{$i}"])) {
                        $failBody[$key] = ['failCustomerKey' => $sendParamType === 1 ? $param['toAddress'] : $param['customerNo'], 'faliReason' => 'E9009'];
                        continue 2; // 次の行のチェックへ
                    }
                }
            }

            if ($sendParamType === 1) {//メールアドレス
                $mailParamValidResult = $this->paramValidTypeMailAddress($param['toAddress']);
                if ($mailParamValidResult !=="") {
                    $failBody[$key] = $mailParamValidResult;
                } else {
                    // 送信先に追加
                    $sendBody[$key] = ['toAddress' => $param['toAddress']];
                }
            } elseif ($sendParamType === 2) {//会員No
                // 会員番号からメールアドレス検索
                $customerResult = $this->customerModel->getCustomerByNo($param['customerNo']);
                // DBデータ存在チェック
                if (empty($customerResult)) {
                    // エラー(DBデータ不正)
                    $failBody[$key] = ['failCustomerKey' => $param['customerNo'], 'faliReason' => 'E5002'];
                    continue;
                }

                // 個別バリデーションチェック
                $mailParamValidResult = $this->paramValidTypeCustomerNo($customerResult, $mailStatus);

                if ($mailParamValidResult !=="") {
                    $failBody[$key] = $mailParamValidResult;
                } else {
                    // 送信先に追加
                    $sendBody[$key] = ['toAddress' => $customerResult['mail']];
                }
            }
        }

        ksort($failBody); // ソートする
        return [$sendBody, $failBody];
    }

    /**
     * param個別バリデーションチェック(メールアドレスタイプ)
     *
     * @param string $mailAddress メールアドレス
     * @param int $mailStatus リクエストメールステータス
     * @return string バリデーション結果
     */
    private function paramValidTypeMailAddress(string $mailAddress) {

        // メールアドレス形式チェック
        if (UtilService::checkMailFormat($mailAddress) === false)  {
            return ['failCustomerKey' => $mailAddress, 'faliReason' => 'E9002'];
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

        // メールアドレス形式チェック
        if (UtilService::checkMailFormat($customer['mail']) === false)  {
            return ['failCustomerKey' => $customer['no'], 'faliReason' => 'E5002'];
        }

        // メールアドレスに紐づくユーザーのcustomer.mail_status取得
        $resultCollection = $this->customerModel->getCustomerMailStatusByMail($customer['mail']);

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
     * メールアドレス重複抽出
     *
     * @param array $sendBody メールアドレス配列
     * @return array 重複している2つ目以降の配列
     */
    private function getDuplicationMailAddress(array $requestParams) {
        $values = array_map(function($a){return $a['toAddress'];}, $requestParams);
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
     * メール配信リクエスト送信
     *
     * @param string $title テンプレートタイトル
     * @param string $text テンプレート文章
     * @param array $sendBody 送信対象
     * @param string $targetType 送信先タイプ
     * @param array $sendBody 送信対象
     * @param array $requestParams リクエスト個別param
     * @param string $requestId リクエストID
     * @return array 重複している2つ目以降の配列
     */
    public function sendPostRequest($title, $templateText, $sendBody, $targetType, $requestParams, $requestId) {

        // 送信元メールアドレス設定
        $fromAddress = $targetType === "kireimo" ? config('myConfig.kireimo_mail_from') : config('myConfig.vielis_mail_from');

        // 差し込み文字設定
        foreach ($sendBody as $key => $param) {
            for ($i=1; $i <= config('myConfig.mail_insert_count'); $i++) {
                if (!empty($requestParams[$key]["insert{$i}"])) $sendBody[$key]["insertion_item{$i}"] = $requestParams[$key]["insert{$i}"];
            }
        }

        // エージェントへのリクエストをログ出力
        $sendJson = json_encode($sendBody, JSON_UNESCAPED_UNICODE);
        \Log::channel('maillogs')->info("[メール配信登録リクエスト]requestId:".$requestId.", タイトル:".$title.", 本文:".$templateText.", 送信先&差し込み文字:".$sendJson.", 送信元:".$fromAddress);

        foreach ($sendBody as $key => $param) {
            $replace_text = $templateText;
            // 差し込み文字へ置換
            for ($i=1; $i <= config('myConfig.mail_insert_count'); $i++) {
                if (mb_strpos($replace_text, "{{insertion_item{$i}}}", null, "UTF-8") !== FALSE) {
                    $replace_text = str_replace("{{insertion_item{$i}}}", $requestParams[$key]["insert{$i}"], $replace_text);
                }
            }

            try {
                // メール送信
                $this->sendPlaneMail($param['toAddress'], $fromAddress, null, [], [], $title, $replace_text);
                // 0.1秒スリープさせる
                usleep(100000);

            } catch (Exception $e) {
                \Log::channel('mailFailure')->info("[メール配信登録異常]requestId:".$requestId."----下記以降の配信登録停止----");
                \Log::channel('mailFailure')->info("[メール配信登録失敗]requestId:".$requestId.", key:".$key.", アドレス:".$param['toAddress'].", ". $e->getMessage());

                // エラーレスポンス返却
                throw new MyApiException(500, "E5001", $key);
            }
        }

        \Log::channel('maillogs')->info("[メール配信登録リクエスト処理 完了]requestId:".$requestId);
    }

    /**
     * エージェント(MTA)にメール(SMTP)リクエスト送信(Plane)
     *
     * @param string $to 送信先アドレス
     * @param string $from 送信元アドレス
     * @param string $fromName 送信元アドレス表示名
     * @param string $cc cc
     * @param string $bcc cc
     * @param string $subject タイトル
     * @param string $body 本文
     *
     * @return void
     */
    public function sendPlaneMail(string $to, string $from, string $fromName = null, $cc = [], $bcc = [], string $subject, string $body) {
        Mail::send(['raw'=>$body], array(), function($message) use ($subject, $to, $from, $fromName, $cc, $bcc){
            $message
            ->to($to)
            ->cc($cc)
            ->bcc($bcc)
            ->subject($subject)
            ->from($from, $fromName);
        });
    }

    /**
     * エージェント(MTA)にメール(SMTP)リクエスト送信(HTML)
     *
     * @param string $to 送信先アドレス
     * @param string $from 送信元アドレス
     * @param string $fromName 送信元アドレス表示名
     * @param string $subject タイトル
     * @param string $body 本文
     *
     * @return void
     */
    public function sendHtmlMail(string $to, string $from, string $fromName = null, $cc = [], $bcc = [], string $subject, string $body) {
        Mail::send(array(), array(), function($message) use ($subject, $to, $from, $fromName, $cc, $bcc, $body){
            $message
            ->to($to)
            ->cc($cc)
            ->bcc($bcc)
            ->subject($subject)
            ->from($from, $fromName)
            ->setBody($body, 'text/html');
        });
    }

}