<?php

namespace App\Service;


class UtilService
{
    /*
     * 年齢チェック(15歳以上であること)
     *
     * @param string $birthday 生年月日
     * @return int 年齢
     */
    public static function checkAge(String $birthday) {
        if (UtilService::getAge($birthday) <= 14) {
            return false;
        }
        return true;
    }

    /*
     * 生年月日から年齢を計算する
     *
     * @param string $birthday 生年月日
     * @return int 年齢
     */
    public static function getAge(String $birthday) {
        $now = (int)date('Ymd');
        $birthday = (int)str_replace("-", "", $birthday);
        $age = 0;
        if($birthday) {
            $age = floor(($now-$birthday)/10000);
        }
        return $age;
    }

    /*
     * 生年月日から指定日付時点の年齢を計算する
     *
     * @param string $birthday 生年月日
     * @param string $targetDate 指定日付
     * @return int 年齢
     */
    public static function getAgeByTargetDate(String $birthday, string $targetDate) {
        $birthday = (int)str_replace("-", "", $birthday);
        $targetDate = (int)str_replace("-", "", $targetDate);
        $age = 0;
        if($birthday) {
            $age = floor(($targetDate-$birthday)/10000);
        }
        return $age;
    }

    /*
     * カスタマイズバリデーションメッセージを定義する
     *
     * @param
     * @return array カスタマイズバリデーションメッセージ
     */
    public static function getValidateMessage() {
        return array(
            'required' => 'E9001',
            'required_without_all' => 'E9001',
            'required_if' => 'E9001',
            'required_with' => 'E9001',
            'integer' => 'E9000',
            'string' => 'E9000',
            'numeric' => 'E9002',
            'email' => 'E9002',
            'regex' => 'E9002',
            'date_format' => 'E9002',
            'boolean' => 'E9002',
            'array' => 'E9002',
            'filled' => 'E9003',
            'min' => 'E9003',
            'max' => 'E9003',
            'in' => 'E9003',
            'between' => 'E9003',
            'digits_between' => 'E9003',
            'size' => 'E9003',
            'after' => 'E9003',
            'after_or_equal' => 'E9003',

        );
    }

    /*
     * 電話番号フォーマット確認(ハイフン無し)
     *
     * @param string $tel 電話番号
     * @return boolean true:正 false:不正
     */
    public static function checkTelFormat($tel) {
        // 電話番号形式チェック
        if (mb_strlen($tel, 'UTF-8') !== 11) {
            return false;
        }
        if (array_search(mb_substr($tel, 0, 3, 'UTF-8'), array("020","060","070","080","090")) === False) {
            return false;
        }
        return true;
    }

    /*
     * メールフォーマット確認
     *
     * @param string $mail メールアドレス
     * @return boolean true:正 false:不正
     */
    public static function checkMailFormat($email) {
        if(!preg_match("/^([a-zA-Z0-9\.\+_-])+@([a-zA-Z0-9_-])+(\.)+([a-zA-Z0-9\._-])+$/", $email)) {
            return false;
        }
        return true;
    }
}