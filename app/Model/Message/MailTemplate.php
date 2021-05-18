<?php

namespace App\Model\Message;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MailTemplate extends Model
{
    protected $connection = 'message_mysql';
    protected $table = 'mail_template';

    public $timestamps = false;


    /**
     * SMSテンプレート取得(メールアドレス付き)
     *
     * @param int $templateId テンプレートID
     * @return MailTemplate
     */
    public function getSmsTemplateText(int $templateId) {
        return $this
        ->join('mail_authority', function($a) {
            $a->on('mail_template.login_id', '=', 'mail_authority.login_id')->where('mail_authority.del_flg', '=', 0 );
        })
        ->where('mail_template.id', $templateId)
        ->where('mail_template.send_type', 2)
        ->where('mail_template.del_flg', 0)
        ->select('mail_template.template_name', 'mail_template.text', 'mail_template.url', 'mail_template.login_id', 'mail_template.group_cd', 'mail_authority.email')
        ->first();
        //->toSql();
    }

    /**
     * メールテンプレート取得(メールアドレス付き)
     *
     * @param int $templateId テンプレートID
     * @return MailTemplate
     */
    public function getMailTemplateText(int $templateId) {
        return $this
        ->where('mail_template.id', $templateId)
        ->where('mail_template.send_type', 1)
        ->where('mail_template.del_flg', 0)
        ->select('mail_template.title', 'mail_template.text', 'mail_template.login_id', 'mail_template.group_cd', 'mail_template.send_type')
        ->first();
        //->toSql();
    }

}
