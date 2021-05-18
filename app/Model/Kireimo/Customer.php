<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;
use App\Exceptions\MyApiException;


class Customer extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'customer';

    //public $timestamps = false;
    const CREATED_AT = 'reg_date';
    const UPDATED_AT = 'edit_date';

    /**
     * 同一人物チェック
     *
     * @param string $mail
     * @param string $tel
     * @param string $birthday
     * @param string $name 姓名(全角スペース含む)
     * @param string $nameKana セイメイ(全角スペース含む)
     * @param string $selfCustomerId 自分の顧客ID
     *
     * @return boolean true:同一人物あり, false:同一人物なし
     */
    public function selectSamePerson($mail, $tel, $birthday, $name, $nameKana) {
        $result = $this
        ->whereRaw("((mail = :mail1 and mail <> '' and  birthday = :birthday1 and birthday <> '0000-00-00')
                     or (tel = :tel1 and tel <> '' and tel <> '-' and tel <> '0' and  birthday = :birthday2 and birthday <> '0000-00-00')
                     or (mail = :mail2 and mail <> '' and tel = :tel2 and tel <> '' and tel <> '-' and tel <> '0')
                     or (name = :name1 and name <> '' and name_kana = :nameKana1 and name_kana <> '' and mail = :mail3 and mail <> '')
                     or (name = :name2 and name <> '' and name_kana = :nameKana2 and name_kana <> '' and tel = :tel3 and tel <> '' and tel <> '-' and tel <> '0')
                     ) and del_flg = 0",
            ['mail1' => $mail, 'birthday1' => $birthday,
                'tel1' => $tel, 'birthday2' => $birthday,
                'mail2' => $mail, 'tel2' => $tel,
                'name1' => $name, 'nameKana1' => $nameKana, 'mail3' => $mail,
                'name2' => $name, 'nameKana2' => $nameKana, 'tel3' => $tel])
        ->select('id')
        ->get();
//         ->toSql();

        return $result;
    }

    /**
     * 顧客情報取得(顧客Idから)
     *
     * @param string $customerId 顧客ID
     * @return Customer
     */
    public function getCustomerById(int $customerId) {
        return $this
        ->where('del_flg', 0)
        ->where('id', $customerId)
        ->select('*')
        ->first();
    }

    /**
     * 顧客情報取得(顧客Noから)
     *
     * @param string $customerNo 会員番号
     * @return Customer
     */
    public function getCustomerByNo(string $customerNo) {
        return $this
        ->where('del_flg', 0)
        ->where('no', $customerNo)
        ->select('*')
        ->first();
    }

    /**
     * メールステータス情報取得(電話番号から)
     *
     * @param string $tel 電話番号(11文字ハイフン無し)
     * @return \Illuminate\Support\Collection
     */
    public function getCustomerMailStatusByTel(string $tel) {
        $telWithHyphen = substr_replace($tel, "-", 3, 0);
        $telWithHyphen = substr_replace($telWithHyphen, "-", 8, 0);
        return $this
        ->where('del_flg', 0)
        ->whereRaw('tel = ? or tel = ?' , [$tel, $telWithHyphen])
        ->select('mail_status')
        ->get();
//         ->toSql();
    }

    /**
     * メールステータス情報取得(メールアドレスから)
     *
     * @param string $mail メールアドレス
     * @return \Illuminate\Support\Collection
     */
    public function getCustomerMailStatusByMail(string $mail) {
        return $this
        ->where('del_flg', 0)
        ->where('mail', $mail)
        ->select('mail_status')
        ->get();
    }

}
