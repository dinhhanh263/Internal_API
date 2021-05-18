<?php

namespace App\Model\Message;

use Illuminate\Database\Eloquent\Model;

class MailAuthority extends Model
{
    protected $connection = 'message_mysql';
    protected $table = 'mail_authority';

    public $timestamps = false;


    /**
     * 指定されたメール認証情報取得
     *
     * @param string $loginId ログインID
     * @return MailAuthority
     */
    public function getAuthInfo($loginId) {
        return $this
        ->where('login_id', $loginId)
        ->where('del_flg', 0)
        ->select('group_cd', 'kireimo_authority_level', 'password')
        ->first();
    }

}
