<?php

namespace App\Model\Kireimo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Exceptions\MyApiException;

class Shop extends Model
{
//     protected $connection = 'kireimo_mysql';
    protected $table = 'shop';

    public $timestamps = false;


    /**
     * 店舗が存在する都道府県の一覧情報取得
     *
     * @return Shop
     */
    public function getReservablePrefList() {
        return $this
        ->where('del_flg', 0)
        ->where('status', 2)
        ->whereNotNull('url')
        ->where('url', '!=', '')
        ->distinct()
        ->select('pref')
        ->orderBy('pref', 'asc')
        ->get();
    }

    /**
     * 指定された店舗情報取得
     *
     * @param int $shopId 店舗ID
     * @return Shop
     */
    public function getShopInfo($shopId) {
        return $this
        ->where('id', $shopId)
        ->where('del_flg', 0)
        ->where('status', 2)
        ->where('assign', 3)
        ->select('*')
        ->first();
    }

    /**
     * 予約可能な全ての店舗情報取得
     *
     * @return Shop
     */
    public function getALLReservableShop() {
        return $this
        ->join('prefectures', function($a) {
            $a->on('prefectures.id', '=', 'shop.pref');
        })
        ->where('shop.del_flg', 0)
        ->where('shop.status', 2)
        ->where('shop.assign', 3)
        ->select('shop.*','prefectures.name as prefectures_name')
        ->get();
    }

    /**
     * カウンセリング予約可能な全ての店舗情報取得
     *
     * @param string $targetDate 基準日付
     * @return Shop
     */
    public function getCounselingReservableShop($targetDate = null) {
        if ($targetDate == null)  {
            $targetDate = date("Y-m-d");
        }

        $currentDateTime = date("Y-m-d H:i");

        return $this
        ->join('prefectures', function($a) {
            $a->on('prefectures.id', '=', 'shop.pref');
        })
        ->where('shop.del_flg', 0)
        ->where('shop.status', 2)
        ->where('shop.assign', 3)
        ->whereRaw(" ? >= str_to_date(shop.rsv_date,'%Y-%m-%d %H:%i')", [$currentDateTime])
        ->whereRaw("( ? <= str_to_date(shop.close_date,'%Y-%m-%d') or shop.close_date is null)", [$targetDate])
        ->select('shop.*','prefectures.name as prefectures_name')
        ->get();
//         ->toSql();
    }

    /**
     * トリートメント予約可能な全ての店舗情報取得
     *
     * @param string $targetDate 基準日付
     * @return Shop
     */
    public function getTreatmentReservableShop($targetDate = null) {
        if ($targetDate == null)  {
            $targetDate = date("Y-m-d");
        }

        $currentDateTime = date("Y-m-d H:i");

        return $this
        ->join('prefectures', function($a) {
            $a->on('prefectures.id', '=', 'shop.pref');
        })
        ->where('shop.del_flg', 0)
        ->where('shop.status', 2)
        ->where('shop.assign', 3)
        ->whereRaw("? >= str_to_date(shop.rsv_date_treatment,'%Y-%m-%d %H:%i')", [$currentDateTime])
        ->whereRaw("(? <= str_to_date(shop.close_date,'%Y-%m-%d') or shop.close_date is null)", [$targetDate])
        ->select('shop.*','prefectures.name as prefectures_name')
            ->get();
    }



    /**
     * @param array $arrayPref 都道府県コード配列
     * @return \Illuminate\Support\Collection
     */
    public function getReservableShopListByArray(Array $arrayPref) {
        $merge_collection = new Collection();
        foreach ($arrayPref as $pref) {
            // 都道府県ごとの店舗情報を取得
            $result = $this->getReservableShopList((int)$pref);

            // 都道府県ごとに取得した店舗情報を同じコレクションに詰める
            $merge_collection->push($result);
        }
        return $merge_collection->collapse();
    }

    /**
     * @param int $pref 都道府県コード
     * @return \Illuminate\Support\Collection
     */
    public function getReservableShopList($pref) {
        return DB::table('shop')
        ->where('pref', $pref)
        ->where('del_flg', 0)
        ->where('status', 2)
        ->whereNotNull('url')
        ->where('url', '!=', '')
        ->whereRaw('CAST(rsv_date AS DATETIME) <= now()')
        ->select('id', 'name', 'pref')
        ->get();
    }
}
