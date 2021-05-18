<?php

namespace App\Model\Message;

use Illuminate\Database\Eloquent\Model;

class MailSendResult extends Model
{
    protected $connection = 'message_mysql';
    protected $table = 'mail_send_result';

    const CREATED_AT = 'reg_date';
    const UPDATED_AT = 'edit_date';


    /**
     * テンプレート取得
     *
     * @param int $requestId リクエストID
     * @param string $deliveryId デリバリーID
     * @param int $registStatus 登録ステータス
     * @param int $templateId テンプレートID
     * @param int $template テンプレートテキスト
     * @param int $registCount 登録件数
     * @param int $registFails 個別エラー項目
     *
     * @return MailTemplate
     */
    public function insert($requestId, $deliveryId, $registStatus, $templateId, $template, $registFails, $registCount, $errorMessage = null) {

        $mailSendResult = new MailSendResult();

        $mailSendResult->request_id = $requestId;
        $mailSendResult->delivery_id = $deliveryId;
        $mailSendResult->regist_status = $registStatus;
        $mailSendResult->template_id = $templateId;
        $mailSendResult->template = $template;
        $mailSendResult->regist_fails =$registFails;
        $mailSendResult->regist_count =$registCount;
        $mailSendResult->error_message = $errorMessage;

        $mailSendResult->save();
    }

}
