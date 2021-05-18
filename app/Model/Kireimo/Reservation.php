<?php

namespace App\Model\Kireimo;

use App\Exceptions\MyApiException;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $table = 'reservation';

    // public $timestamps = false;
    const CREATED_AT = 'reg_date';
    const UPDATED_AT = 'edit_date';

    /**
     * 店舗予約状況検索
     *
     * @param string $reservationDate 予約日
     * @param string $shopId 店舗ID
     * @param array $room_list 予約ルームリスト
     * @param int $startTime 開始時間
     * @param int $length 施術時間(枠数)
     * @param int $selfReservationId 自身の予約ID
     *
     * @return Reservation
     */
    public function getShopReservationInfo(string $reservationDate, string $shopId, array $room_list, int $startTime, int $length, int $selfReservationId = null) {
        // 1人だったら2枠、2人だったら3枠で予約テーブル空き検索
        return $this
        ->where('id', '<>', $selfReservationId)
        ->where('type', '!=', 3)
        ->where('del_flg', 0)
        ->where('hope_date', $reservationDate)
        ->where('shop_id', $shopId)
        ->whereIn('room_id', $room_list)
        ->whereRaw('( (hope_time< ? AND hope_time+length > ? ) OR (hope_time < ? AND hope_time+length > ?) OR (hope_time >= ? AND hope_time+length <= ?) )',[$startTime, $startTime, ($startTime + $length), ($startTime + $length), $startTime, ($startTime + $length)])
        ->select('room_id')
        ->get();
        //->toSql();
        // ->getBindings();
    }

    /**
     * 予約情報取得(予約Idから)
     *
     * @param int $reservationId 予約ID
     * @return Reservation
     */
    public function getReservationById(int $reservationId) {
        return $this
        ->where('del_flg', 0)
        ->where('id', $reservationId)
        ->select('*')
        ->first();
    }

    /**
     * 有効な予約情報取得(typeは全て返却)
     *
     * @param int $reservationId 予約ID
     * @return Reservation
     */
    public function getActiveReservation(int $reservationId) {
        return $this
        ->where('del_flg', 0)
        ->where('status', 0)
        ->where('rsv_status', 0)
        ->where('hope_date', '>=', date('Y-m-d'))
        ->where('id', $reservationId)
        ->select('*')
        ->first();
    }

    /**
     * 予約キャンセル
     *
     * @param int $reservationId 予約ID
     *
     * @return void
     */
    public function cancel(int $reservationId) {
        $result = $this
        ->where('del_flg', 0)
        ->where('type', '<>', 3)
        ->where('id', $reservationId)
        ->select('type')
        ->first();

        if (empty($result)) {
            \Log::channel('errorlogs')->error("キャンセル対象データが存在しないエラー");
            throw new MyApiException(400, "E5002", null);
        }

        $this
        ->where('del_flg', 0)
        ->where('type', '<>', 3)
        ->where('id', $reservationId)
        ->update(['type' => 3, 'cancel_before_type' => $result->type, 'cancel_date' => date('Y-m-d')]);
    }

    /**
     * 顧客の将来の予約取得(カウンセリング含む)
     *
     * @param int $customerId 顧客ID
     * @param int $selfReservationId 自身の予約ID
     *
     * @return Reservation
     */
    public function getFutureReservation(int $customerId, int $selfReservationId = null) {
        return $this
        ->leftJoin('course', 'course.id', '=', 'reservation.course_id')
        ->join('shop', 'shop.id', '=', 'reservation.shop_id')
        ->where('reservation.customer_id', $customerId)
        ->where('reservation.id', '<>', $selfReservationId)
        ->whereIn('reservation.type', array(1,2))
        ->where('reservation.rsv_status', 0)
        ->where('reservation.status', 0)
        ->where('reservation.del_flg', 0)
        ->whereRaw('(course.del_flg = 0 or course.del_flg is NULL)')
        ->where('reservation.hope_date', '>=', date('Y-m-d'))
        ->select('reservation.*', 'course.treatment_type as course_treatment_type', 'course.name as course_name', 'course.length as course_length'
            , 'shop.name as shop_name', 'shop.address as shop_address', 'shop.pref as shop_pref')
        ->orderBy('reservation.reg_date', 'desc')
        ->get();
    }

    /**
     * 顧客の将来の予約取得(トリートメントのみ)
     *
     * @param int $customerId 顧客ID
     * @param int $selfReservationId 自身の予約ID
     *
     * @return Reservation
     */
    public function getFutureTreatmentByCustomerId(int $customerId, int $selfReservationId = null) {
        return $this
        ->where('customer_id', $customerId)
        ->where('id', '<>', $selfReservationId)
        ->where('type', 2)
        ->where('rsv_status', 0)
        ->where('status', 0)
        ->where('del_flg', 0)
        ->where('hope_date', '>=', date('Y-m-d'))
        ->select('*')
        ->orderBy('reg_date', 'desc')
        ->get();
    }

    /**
     * 同契約の将来の予約取得(トリートメントのみ)
     *
     * @param int $contractId 契約ID
     *
     * @return Reservation
     */
    public function getFutureTreatmentReservation(int $contractId) {
        return $this
        ->where('contract_id', $contractId)
        ->where('type', 2)
        ->where('rsv_status', 0)
        ->where('status', 0)
        ->where('del_flg', 0)
        ->where('reservation.hope_date', '>=', date('Y-m-d'))
        ->select('*')
        ->orderBy('reg_date', 'desc')
        ->get();
    }

    /**
     * 顧客の将来のカウンセリング予約取得
     *
     * @param int $customerId 顧客ID
     *
     * @return Reservation
     */
    public function getFutureCounselingReservation(int $customerId) {
        return $this->where('customer_id', $customerId)
        ->where('type', 1)
        ->where('rsv_status', 0)
        ->where('status', 0)
        ->where('del_flg', 0)
        ->where('hope_date', '>=', date('Y-m-d'))
        ->select('id')
        ->orderBy('reg_date', 'desc')
        ->get();
    }

    /**
     * 顧客の将来のカウンセリング予約キャンセル
     *
     * @param int $customerId 顧客ID
     *
     * @return void
     */
    public function cancelFutureCounseling(int $customerId) {
        return $this->where('customer_id', $customerId)
        ->where('type', 1)
        ->where('del_flg', 0)
        ->where('hope_date', '>=', date('Y-m-d'))
        ->update(['type' => 3, 'cancel_before_type' => 1, 'cancel_date' => date('Y-m-d')]);
    }


    /**
     * 部屋指定予約検索
     *
     * @param string $reservationDate 予約日
     * @param string $shopId 店舗ID
     * @param array $room_list 予約ルームリスト
     * @param int $selfReservationId 自身の予約ID
     *
     * @return Reservation
     */
    public function getReservationByRoom(string $reservationDate, string $shopId, array $room_list, int $selfreservationId = null) {
        return $this
        ->where('id', '<>', $selfreservationId)
        ->where('type', '<>', 3)
        ->where('del_flg', 0)
        ->where('hope_date', $reservationDate)
        ->where('shop_id', $shopId)
        ->whereIn('room_id', $room_list)
        ->select('room_id', 'hope_time', 'length')
        ->get();
        //->toSql();
        // ->getBindings();
    }

    /**
     * 最終施術取得
     *
     * @param int $contractId 契約ID
     *
     * @return Reservation
     */
    public function getLastTreatment(int $contractId) {
        return  $this
        ->where('del_flg', 0)
        ->whereIn('type', array(2,30)) // 2:トリートメント 30:当て漏れ
        ->where('status', 11) // 11:来店
//         ->where('length', '>=', 2)
        ->where('contract_id', $contractId)
        ->where('hope_date', '<=', 'CURDATE()')
        ->select('*')
        ->orderBy('hope_date', 'DESC')
        ->first();
    }

    /**
     * 最終消化取得
     *
     * @param int $contractId 契約ID
     *
     * @return Reservation
     */
    public function getLastTreatmentOrPenalty(int $contractId) {
        return  $this
        ->join('sales', 'sales.id', '=', 'reservation.sales_id')
        ->where('reservation.del_flg', 0)
        ->where('sales.del_flg', 0)
        ->whereIn('sales.type', array(2,3,14,15))
        ->where('reservation.sales_id', '>', 0)
        ->where('sales.r_times', '<>', 0)
//         ->where('reservation.length', '>=', 2)
        ->where('sales.contract_id', $contractId)
        ->where('reservation.hope_date', '<=', 'CURDATE()')
        ->orderBy('reservation.id', 'DESC')
        ->first();
    }

    /**
     * 最終ペナルティ消化取得
     *
     * @param int $contractId 契約ID
     *
     * @return Reservation
     */
    public function getLastCancelPenalty(int $contractId) {
        return  $this
        ->join('sales', 'sales.id', '=', 'reservation.sales_id')
        ->where('reservation.del_flg', 0)
        ->where('sales.del_flg', 0)
        ->where('sales.r_times', '>', 0)
        ->whereRaw('(reservation.type > 2 or (reservation.type = 2 AND reservation.status = 1))')
        ->where('sales.contract_id', $contractId)
        ->select('reservation.*')
        ->orderByRaw('reservation.hope_date DESC, reservation.hope_time DESC')
        ->first();
    }

    /**
     * 前回の予約取得
     *
     * @param int $contractId 契約ID
     *
     * @return Reservation
     */
    public function getLastReservation(int $contractId) {
        return  $this
        ->where('del_flg', 0)
        ->where('contract_id', $contractId)
        ->select('*')
        ->orderBy('id', 'DESC')
        ->first();
    }

    /**
     * 前回の予約取得(顧客IDから)
     *
     * @param int $customerId 顧客ID
     *
     * @return Reservation
     */
    public function getLastReservationByCustomerId(int $customerId) {
        return  $this
        ->where('del_flg', 0)
        ->where('customer_id', $customerId)
        ->select('*')
        ->orderBy('id', 'DESC')
        ->first();
    }

}
