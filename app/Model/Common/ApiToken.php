<?php

namespace App\Model\Common;

use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    protected $connection = 'common_mysql';
    protected $table = 'api_token';

    public $timestamps = false;


    /**
     * APIトークン情報取得
     *
     * @param string $user ユーザー
     * @return ApiToken
     */
    public function getApiTokenInfo($user) {
        return $this
        ->where('user', $user)
        ->where('del_flg', 0)
        ->select('*')
        ->first();
    }

    /**
     * APIトークン情報取得(gipから)
     *
     * @param string $gip gip
     * @return ApiToken
     */
    public function getApiTokenInfoByGip($gip) {
        if ($gip == "") {
            return null;
        }

        return $this
        ->where('gip', 'like', "%{$gip}%")
        ->where('del_flg', 0)
        ->select('*')
        ->first();
    }

}
