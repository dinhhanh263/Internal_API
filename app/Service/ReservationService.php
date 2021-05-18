<?php

namespace App\Service;

use App\Model\Kireimo\Adcode;
use App\Model\Kireimo\Contract;
use App\Model\Kireimo\Course;
use App\Model\Kireimo\Customer;
use App\Model\Kireimo\Reservation;
use App\Model\Kireimo\Sales;
use App\Model\Kireimo\Shop;
use App\Exceptions\MyApiException;


class ReservationService
{
    private $shopModel;
    private $reservationModel;
    private $customerModel;
    private $contractModel;
    private $salesModel;
    private $adcode;

    public function __construct(Shop $shopModel, Reservation $reservationModel, Customer $customerModel, Contract $contractModel, Sales $salesModel, Adcode $adcodeModel)
    {
        $this->shopModel = $shopModel;
        $this->reservationModel = $reservationModel;
        $this->customerModel = $customerModel;
        $this->contractModel = $contractModel;
        $this->salesModel = $salesModel;
        $this->adcodeModel = $adcodeModel;
    }

    /**
     * 店舗予約空き状況確認(カウンセリング)
     *
     * @param int $shopObject 店舗オブジェクト
     * @param string $reservationDate 予約日付
     * @param int $selfreservationId 無視する予約ID(更新時用)
     *
     * @return array $emptyRoomSize 空き部屋数, $roomSize 部屋数, $reserveableRoomId 予約可能ルームID
     */
    public function checkReservable($shopObject, $reservationDate, $selfreservationId = null)
    {
        // 予約人数に応じた施術時間設定
        $length = 2; // 2:60分固定に修正

        // カウンセリング可能ルーム数取得
        $room_list = array();
        for ($i = 1; $i <= $shopObject->counseling_rooms; $i++)   $room_list[] = (int)("1".$i);
        for ($i = 1; $i <= $shopObject->vip_rooms; $i++)          $room_list[] = (int)("2".$i);
        $maxRoomSize = count($room_list); // 店舗部屋数

        // 店舗が予約可能なステータスかチェック
        $resultCollection = $this->shopModel->getCounselingReservableShop($reservationDate);
        $reservableStatus = false;
        foreach($resultCollection as $value) {
            if ($shopObject->id == $value['id']) {
                $reservableStatus = true;
                break;
            }
        }

        // 過去日でないことチェック
        if ($reservationDate < date("Y-m-d")) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E9011", null);
        }

        // 予約検索(日, 店舗, 部屋時間タイプ)
        $reservationResult = $this->reservationModel->getReservationByRoom($reservationDate, $shopObject->id, $room_list, $selfreservationId);
        // 該当日の店舗の予約一覧を部屋ID毎にグループ化
        $groupedReservation = $reservationResult->groupBy("room_id");

        $result = [];
        // 予約可能タイムコードから順次予約の空き確認する
        foreach (config('myConfig.counseling_time_list') as $reservationTimeCd => $value) {
            // 開店日より前 & 店舗が予約可能なステータスでない場合 conf設定の非営業日 は空きなしを返す
            if (($reservationDate < $shopObject->open_date) || ($shopObject->close_date !== null && $reservationDate > $shopObject->close_date)
                || $reservableStatus === false
                || (config('myConfig.gClosed') !== null && array_key_exists($reservationDate, config('myConfig.gClosed')) && in_array($shopObject->id, config('myConfig.gClosed')[$reservationDate]))) {
                $result[$reservationTimeCd]['emptySize'] = 0;
                $result[$reservationTimeCd]['maxSize'] = $maxRoomSize;
                continue;
            }

            // 当日予約可能時間チェック
            if ($reservationDate == date("Y-m-d") && config('myConfig.counseling_time_list')[$reservationTimeCd] <= date("H:i")) {
                $result[$reservationTimeCd]['emptySize'] = 0;
                $result[$reservationTimeCd]['maxSize'] = $maxRoomSize;
                continue;
            }

            $reservedRooms = []; // この時間の予約済み部屋リスト
            // 部屋ID毎にグループ化した予約一覧を順次確認
            foreach ($groupedReservation as $roomReservations) {
                // 部屋の個別予約が予約希望時間と一致しているか確認
                foreach ($roomReservations as $reservation) {
                    // タイムコード+length内に重複する既存予約の確認
                    if ($reservation->hope_time < $reservationTimeCd && ($reservation->hope_time + $reservation->length - 1) >= $reservationTimeCd) {
                        // 重複する場合部屋IDを取得
                        $reservedRooms[] = $reservation->room_id;
                        continue 2;
                    }
                    if ($reservation->hope_time <= ($reservationTimeCd + $length - 1) && ($reservation->hope_time + $reservation->length - 1) > ($reservationTimeCd + $length - 1)) {
                        // 重複する場合部屋IDを取得
                        $reservedRooms[] = $reservation->room_id;
                        continue 2;
                    }
                    if ($reservation->hope_time >= $reservationTimeCd && ($reservation->hope_time + $reservation->length - 1) <= ($reservationTimeCd + $length - 1)) {
                        // 重複する場合部屋IDを取得
                        $reservedRooms[] = $reservation->room_id;
                        continue 2;
                    }
                }
            }

            $emptyRoomSize = count($room_list);  // 残り部屋空き数
            $reserveableRoomId = null; // 予約可能ルームID
            foreach($room_list as $tmp_room) {
                if( !in_array($tmp_room, $reservedRooms) ) {
                    // 空きroomあり
                    if ($reserveableRoomId === null) {
                        $reserveableRoomId = $tmp_room;
                    }
                } else {
                    $emptyRoomSize --;
                }
            }
            $result[$reservationTimeCd]['emptySize'] = $emptyRoomSize;
            $result[$reservationTimeCd]['maxSize'] = $maxRoomSize;
            $result[$reservationTimeCd]['reserveableRoomId'] = $reserveableRoomId;
        }

        return $result;
    }

    /**
     * 店舗予約空き状況確認(トリートメント)
     *
     * @param string $reservationDate 予約日付
     * @param Course $courseObject
     * @param Shop $shopObject
     * @param int $customerId
     * @param int $selfreservationId 無視する予約ID(更新時用)
     *
     * @return array $emptyRoomSize 空き部屋数, $roomSize 部屋数, $reserveableRoomId 予約可能ルームID
     */
    public function checkTreatmentReservable(string $reservationDate, Course $courseObject, Shop $shopObject, int $customerId, int $selfreservationId = null)
    {
        // トリートメント可能ルーム数
        $room_list = array();
        // タイムリスト
        $timeList = array();
        if ($courseObject->weekdays_plan_type === 0) { // 平日とくとくプラン(90分しかない想定)
            for ($i = 1; $i <= $shopObject->ninety_time_rooms; $i++)   $room_list[] = (int)("3".$i);
            $timeList = config('myConfig.tokutoku_90_room_time_list');
        } elseif ($courseObject->length === 1) { // 30分コース
            for ($i = 1; $i <= $shopObject->thirty_time_rooms; $i++)   $room_list[] = (int)("6".$i);
            $timeList = config('myConfig.30_room_time_list');
        } elseif ($courseObject->length === 2) { // 60分コース
            for ($i = 1; $i <= $shopObject->sixty_time_rooms; $i++)   $room_list[] = (int)("5".$i);
            $timeList = config('myConfig.60_room_time_list');
        } elseif ($courseObject->length === 3) { // 90分コース
            for ($i = 1; $i <= $shopObject->ninety_time_rooms; $i++)   $room_list[] = (int)("3".$i);
            $timeList = config('myConfig.90_room_time_list');
        } else {
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002", null);
        }

        $maxRoomSize = count($room_list); // 店舗部屋数

        // 店舗が予約可能なステータスかチェック
        $resultCollection = $this->shopModel->getTreatmentReservableShop($reservationDate);
        $reservableStatus = false;
        foreach($resultCollection as $value) {
            if ($shopObject->id == $value['id']) {
                $reservableStatus = true;
                break;
            }
        }

        // 過去日でないことチェック
        if ($reservationDate < date("Y-m-d")) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E9011", null);
        }

        // 予約検索(日, 店舗, 部屋時間タイプ)
        $reservationResult = $this->reservationModel->getReservationByRoom($reservationDate, $shopObject->id, $room_list, $selfreservationId);
        // 該当日の店舗の予約一覧を部屋ID毎にグループ化
        $groupedReservation = $reservationResult->groupBy("room_id");

        $result = [];
        // 予約可能タイムコードから順次予約の空き確認する
        foreach ($timeList as $reservationTimeCd => $value) {

            // 平日とくとくプランチェック(土日祝の場合空き0を返す)
            if ($courseObject->weekdays_plan_type === 0) {
                $result_day_of_week = date('w',strtotime($reservationDate));
                $result_year = date('Y', strtotime($reservationDate));
                $result_month_day = date('m-d', strtotime($reservationDate));
                $holidayList = config('myConfig.holiday_list');
                if (array_search((int)$result_day_of_week, array(0, 6), TRUE) !== FALSE
                    || (array_key_exists($result_year, $holidayList) && array_search($result_month_day, $holidayList[$result_year]) !== false)) {
                        $result[$reservationTimeCd]['emptySize'] = 0;
                        $result[$reservationTimeCd]['maxSize'] = $maxRoomSize;
                        continue;
                    }
            }

            // 開店日より前 & 店舗が予約可能なステータスでない場合 conf設定の非営業日 は空きなしを返す
            if (($reservationDate < $shopObject->open_date) || ($shopObject->close_date !== null && $reservationDate > $shopObject->close_date)
                || $reservableStatus === false
                || (config('myConfig.gClosed') !== null && array_key_exists($reservationDate, config('myConfig.gClosed')) && in_array($shopObject->id, config('myConfig.gClosed')[$reservationDate]))) {
                $result[$reservationTimeCd]['emptySize'] = 0;
                $result[$reservationTimeCd]['maxSize'] = $maxRoomSize;
                continue;
            }

            // 当日予約可能時間チェック
            if ($reservationDate == date("Y-m-d") && $timeList[$reservationTimeCd] <= date("H:i")) {
                $result[$reservationTimeCd]['emptySize'] = 0;
                $result[$reservationTimeCd]['maxSize'] = $maxRoomSize;
                continue;
            }

            $reservedRooms = []; // この時間の予約済み部屋リスト
            // 部屋ID毎にグループ化した予約一覧を順次確認
            foreach ($groupedReservation as $roomReservations) {
                // 部屋の個別予約が予約希望時間と一致しているか確認
                foreach ($roomReservations as $reservation) {
                    // タイムコード+length内に重複する既存予約の確認
                    if ($reservation->hope_time < $reservationTimeCd && ($reservation->hope_time + $reservation->length - 1) >= $reservationTimeCd) {
                        // 重複する場合部屋IDを取得
                        $reservedRooms[] = $reservation->room_id;
                        continue 2;
                    }
                    if ($reservation->hope_time <= ($reservationTimeCd + $courseObject->length - 1) && ($reservation->hope_time + $reservation->length - 1) > ($reservationTimeCd + $courseObject->length - 1)) {
                        // 重複する場合部屋IDを取得
                        $reservedRooms[] = $reservation->room_id;
                        continue 2;
                    }
                    if ($reservation->hope_time >= $reservationTimeCd && ($reservation->hope_time + $reservation->length - 1) <= ($reservationTimeCd + $courseObject->length - 1)) {
                        // 重複する場合部屋IDを取得
                        $reservedRooms[] = $reservation->room_id;
                        continue 2;
                    }
                }
            }

            $emptyRoomSize = count($room_list);  // 残り部屋空き数
            $reserveableRoomId = null; // 予約可能ルームID
            foreach($room_list as $tmp_room) {
                // 部屋リストから順次、該当部屋IDが予約済み部屋リストに含まれているか確認
                if( !in_array($tmp_room, $reservedRooms) ) {
                    // 空きroomあり
                    if ($reserveableRoomId === null) {
                        // 最初の空きの場合予約可能ルームIDを設定する
                        $reserveableRoomId = $tmp_room;
                    }
                } else {
                    // 空きroomなし
                    $emptyRoomSize --;
                }
            }

            // 顧客の将来の予約取得(同一顧客同時間帯予約有無チェック用)
            $futureReservationCollection = $this->reservationModel->getFutureTreatmentByCustomerId($customerId, $selfreservationId);

            // 同一顧客同時間帯予約有無チェック
            foreach ($futureReservationCollection as $futureReservation) {
                if ($reservationDate === $futureReservation->hope_date) {
                    // タイムコード+length内に重複する既存予約の確認
                    if ($futureReservation->hope_time < $reservationTimeCd && ($futureReservation->hope_time + $futureReservation->length - 1) >= $reservationTimeCd) {
                        $emptyRoomSize = 0;
                        break;
                    }
                    if ($futureReservation->hope_time <= ($reservationTimeCd + $courseObject->length - 1) && ($futureReservation->hope_time + $futureReservation->length - 1) > ($reservationTimeCd + $courseObject->length - 1)) {
                        $emptyRoomSize = 0;
                        break;
                    }
                    if ($futureReservation->hope_time >= $reservationTimeCd && ($futureReservation->hope_time + $futureReservation->length - 1) <= ($reservationTimeCd + $courseObject->length - 1)) {
                        $emptyRoomSize = 0;
                        break;
                    }
                }
            }

            $result[$reservationTimeCd]['emptySize'] = $emptyRoomSize;
            $result[$reservationTimeCd]['maxSize'] = $maxRoomSize;
            $result[$reservationTimeCd]['reserveableRoomId'] = $reserveableRoomId;
        }

        return $result;
    }

    // カウンセリング店舗予約空き状況確認(日時指定)
    public function checkReservableByDateTime($shopObject, $reservationDate, $reservationTimeCd, $selfreservationId = null) {
        $result = $this->checkReservable($shopObject, $reservationDate, $selfreservationId ?? null);
        return array($result[$reservationTimeCd]['emptySize'], $result[$reservationTimeCd]['reserveableRoomId']?? null);
    }

    // トリートメント店舗予約空き状況確認(日時指定)
    public function checkTreatmentReservableByDateTime($reservationDate, $reservationTimeCd, Course $courseObject, Shop $shopObject, int $customerId, $selfreservationId = null) {
        $result = $this->checkTreatmentReservable($reservationDate, $courseObject, $shopObject, $customerId, $selfreservationId ?? null);
        return array($result[$reservationTimeCd]['emptySize'], $result[$reservationTimeCd]['reserveableRoomId']?? null);
    }

    /**
     * カウンセリング予約データ登録
     *
     * @param array $reservationRequest
     * @param Customer $customerObject
     * @param int $inviteCustomerNo
     * @param string $adcode
     *
     * @return int id
     */
    public function registCounselingReserve(array $reservationRequest, Customer $customerObject, Shop $shopObject, string $inviteCustomerNo = null, string $adcode = null) {
        \DB::beginTransaction();
        try {
            // 予約空き確認
            list($emptyRoomSize, $reserveableRoomId) = $this->checkReservableByDateTime($shopObject, $reservationRequest['hopeDate'], $reservationRequest['hopeTimeCd']);

            if ($emptyRoomSize == 0) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E9012", null);
            }

            // 将来の予約をキャンセル
            $cancelResult = $this->cancelFutureCounseling($customerObject->id);
            if ($cancelResult === true || $customerObject->rebook_flg === true || $customerObject->rebook_flg === 1) {
                $this->reservationModel->rebook_flg = 1;
            }

            // DBインサート用パラメーター設定
            $this->reservationModel->customer_id = $customerObject->id;
            $this->reservationModel->type = 1; // 1:カウンセリング予約 固定
            $this->reservationModel->shop_id = $reservationRequest['shopId'];
            $this->reservationModel->room_id = $reserveableRoomId;
            $this->reservationModel->hope_date = $reservationRequest['hopeDate'];
            $this->reservationModel->hope_time = $reservationRequest['hopeTimeCd'];
            $this->reservationModel->length = 2; // 60分固定に修正
            $this->reservationModel->memo2 = $reservationRequest['memo'] ?? "";

            // adcode設定
            if ($inviteCustomerNo !== null) {
                // 友達紹介用広告コード設定
                $this->reservationModel->adcode = config('myConfig.introduction_adcode');
            } elseif ($adcode !== null) {
                $adcodeObject = $this->adcodeModel->getAdcodeId($adcode);
                if (empty($adcodeObject)) {
                    $this->reservationModel->adcode = "-";
                } else {
                    $this->reservationModel->adcode = $adcodeObject['id'];
                }
            }

            $this->reservationModel->new_flg = 1;

            if ($customerObject->hopes_discount !== null) $this->reservationModel->hopes_discount = $customerObject->hopes_discount;
            if ($customerObject->hope_campaign !== null) $this->reservationModel->hope_campaign = $customerObject->hope_campaign;

            // DB登録
            $this->reservationModel->save();

            \DB::commit();

        } catch (\Exception $e){
            \DB::rollBack();

            // エラーレスポンス返却
            throw $e;
        }

        return $this->reservationModel->id;
    }

    /**
     *  トリートメント予約データ登録
     *
     * @param array $reservationRequest
     * @param Customer $customerObject
     * @param Contract $contract
     * @param Course $course
     * @param Shop $shopObject
     *
     * @return int id
     */
    public function registTreatmentReserve(array $reservationRequest, Customer $customerObject, Contract $contractObject, Course $courseObject, Shop $shopObject) {
        \DB::beginTransaction();
        try {
            // 脱毛の場合、既に予約が入っているとエラー
            if($courseObject->treatment_type === 0 && $this->reservationModel->getFutureTreatmentReservation($contractObject->id)->count() >= $courseObject->reservation_max_times) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E9015", null);

            } elseif ($courseObject->treatment_type === 1) {
                // エステの場合、既に3件予約が入っているとエラー
                if ($this->reservationModel->getFutureTreatmentReservation($contractObject->id)->count() >= $courseObject->reservation_max_times) {
                    // エラーレスポンス返却
                    throw new MyApiException(400, "E9015", null);
                }
                // 予約中も含めた回数チェック
                $futureCount = $this->reservationModel->getFutureTreatmentReservation($contractObject->id)->count();
                if (($contractObject->r_times + $futureCount) >= $contractObject->times && $courseObject->times != 0){
                    // エラーレスポンス返却
                    throw new MyApiException(400, "E9015", null);
                }
            }

            // 予約空き確認
            list($emptyRoomSize, $reserveableRoomId) = $this->checkTreatmentReservableByDateTime($reservationRequest['hopeDate'], $reservationRequest['hopeTimeCd'], $courseObject, $shopObject, $customerObject->id);

            if ($emptyRoomSize == 0) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E9012", null);
            }

            // 前回の予約取得(顧客IDから)
            $lastReservationObject = $this->reservationModel->getLastReservationByCustomerId($customerObject->id);

            // DBインサート用パラメーター設定
            $this->reservationModel->contract_id = $contractObject->id;
            $this->reservationModel->customer_id = $customerObject->id;
            $this->reservationModel->shop_id = $reservationRequest['shopId'];
            $this->reservationModel->room_id = $reserveableRoomId;
            $this->reservationModel->course_id = $courseObject->id;
            $this->reservationModel->type = 2; // 2:トリートメント予約 固定
            $this->reservationModel->rsv_status = 0;
            $this->reservationModel->hope_date = $reservationRequest['hopeDate'];
            $this->reservationModel->hope_time = $reservationRequest['hopeTimeCd'];
            $this->reservationModel->length = $courseObject->length; // コースのlengthを設定
            $this->reservationModel->persons = 1;
            $this->reservationModel->route = 5; // 5:Mypage
            $this->reservationModel->status = 0;
            $this->reservationModel->memo2 = $lastReservationObject->memo2 ?? "";

            // DB登録
            $this->reservationModel->save();

            \DB::commit();

        } catch (\Exception $e){
            \DB::rollBack();

            // エラーレスポンス返却
            throw $e;
        }

        return $this->reservationModel->id;
    }

    /**
     * カウンセリング予約更新
     *
     * @param int $reservationId
     * @param array $requestData
     * @param Shop $shopObject
     *
     * @return int id
     */
    public function updateCounselingReserve(int $reservationId, array $requestData, Shop $shopObject) {
        // 予約空き確認
        list($emptyRoomSize, $reserveableRoomId) = $this->checkReservableByDateTime($shopObject, $requestData['hopeDate'], $requestData['hopeTimeCd'], $reservationId);

        if ($emptyRoomSize == 0) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E9012", null);
        }

        $this->reservationModel->shop_id = $requestData['shopId'];
        $this->reservationModel->room_id = $reserveableRoomId;
        $this->reservationModel->hope_date = $requestData['hopeDate'];
        $this->reservationModel->hope_time = $requestData['hopeTimeCd'];
        $this->reservationModel->length = 2; // 60分固定に修正
        $this->reservationModel->rebook_flg = 1;
        $this->reservationModel->act_flg = 1;

        $resultCount = $this->reservationModel
        ->where('id', $reservationId)
        ->where('type', '<>', 3)
        ->where('del_flg', 0)
        ->update($this->reservationModel->getAttributes());

        if ($resultCount == 0) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002", null);
        }
    }

    /**
     * トリートメント予約更新
     *
     * @param int $reservationId
     * @param array $requestData
     * @param Course $courseObject
     * @param Shop $shopObject
     * @param Customer $customerObject
     *
     * @return int id
     */
    public function updateTreatmentReserve(int $reservationId, array $requestData, Course $courseObject, Shop $shopObject, Customer $customerObject) {
        // 予約空き確認
        list($emptyRoomSize, $reserveableRoomId) = $this->checkTreatmentReservableByDateTime($requestData['hopeDate'], $requestData['hopeTimeCd'], $courseObject, $shopObject, $customerObject->id, $reservationId);

        if ($emptyRoomSize == 0) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E9012", null, "予約空きなしエラー");
        }

        $this->reservationModel->shop_id = $requestData['shopId'];
        $this->reservationModel->room_id = $reserveableRoomId;
        $this->reservationModel->hope_date = $requestData['hopeDate'];
        $this->reservationModel->hope_time = $requestData['hopeTimeCd'];

        $resultCount = $this->reservationModel
        ->where('id', $reservationId)
        ->where('del_flg', 0)
        ->where('type', 2)
        ->where('status', 0)
        ->where('rsv_status', 0)
        ->update($this->reservationModel->getAttributes());

        if ($resultCount == 0) {
            // エラーレスポンス返却
            throw new MyApiException(400, "E5002", null);
        }
    }

    /**
     * 顧客の将来のカウンセリング予約をキャンセル
     *
     * @param int $customerId
     *
     * @return boolean true:キャンセル実行 false:該当なし
     */
    private function cancelFutureCounseling(int $customerId) {
        // 将来の予約をキャンセル前にid取得しcancel_before_idにセット
        $resultCollection =  $this->reservationModel->getFutureCounselingReservation($customerId);
        if ($resultCollection->count() !== 0) {
            $this->reservationModel->cancel_before_id = $resultCollection[0]['id'];
        }

        // 将来の予約をキャンセル
        $resultCount = $this->reservationModel->cancelFutureCounseling($customerId);
        return $resultCount >= 1 ? true : false;
    }

    /**
     * トリートメント予約をペナルティキャンセル
     *
     * @param Reservation $reservationObject
     * @param Contract $contractObject
     * @param Course $courseObject
     *
     * @return void
     */
    public function cancelPenaltyTreatment(Reservation $reservationObject, Contract $contractObject, Course $courseObject) {
        \DB::beginTransaction();
        try {

            // 売上テーブルインサート
            $this->salesModel->contract_id = $contractObject->id;
            $this->salesModel->type = 14; // 14:当日キャンセル
            $this->salesModel->reservation_id = $reservationObject->id;
            $this->salesModel->customer_id = $reservationObject->customer_id;
            $this->salesModel->shop_id = $reservationObject->shop_id;
            $this->salesModel->course_id = $courseObject->id;
            $this->salesModel->times = $contractObject->times;
            $this->salesModel->fixed_price = $contractObject->fixed_price;
            $this->salesModel->discount = $contractObject->discount;
            $this->salesModel->pay_date = $reservationObject->hope_date;
            $this->salesModel->r_times = $contractObject->r_times + 1;

            // DB登録
            $this->salesModel->save();

            // 予約テーブル更新
            $this->reservationModel->status = 1;
            $this->reservationModel->rsv_status = 14;
            $this->reservationModel->room_id = 41; // 部屋を開けるためroom_id:41に固定する
            $this->reservationModel->reg_flg = 1;
            $this->reservationModel->sales_id = $this->salesModel->id;

            $resultCount = $this->reservationModel
            ->where('id', $reservationObject->id)
            ->where('del_flg', 0)
            ->where('type', 2)
            ->where('status', 0)
            ->where('rsv_status', 0)
            ->update($this->reservationModel->getAttributes());

            if ($resultCount === 0) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E5002", null);
            }

            // 契約テーブル更新
            $this->contractModel->r_times = $contractObject->r_times + 1;
            $this->contractModel->latest_date = date("Y-m-d");

            $resultCount2 = $this->contractModel
            ->where('id', $contractObject->id)
            ->where('del_flg', 0)
            ->update($this->contractModel->getAttributes());

            if ($resultCount2 === 0) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E5002", null);
            }

            \DB::commit();
        } catch (\Exception $e){
            \DB::rollBack();

            // エラーレスポンス返却
            throw $e;
        }
    }

    // 過去契約も含めた最終施術取得
    public function getLastTreatmentWithOld(Contract $contractObject) {
        $lastTreatment = $this->reservationModel->getLastTreatment($contractObject->id);
        // プラン移行後の初回用に過去の契約も参照する
        $tmp_old_contract_id = $contractObject->old_contract_id;
        for ($i = 1; empty($lastTreatment); $i++) {
            if ($tmp_old_contract_id !== 0) {
                $tmpOldContract = $this->contractModel->getContract($tmp_old_contract_id);
                if (empty($tmpOldContract)) {
                    // エラーレスポンス返却
                    throw new MyApiException(400, "E5002");
                }
                $lastTreatment = $this->reservationModel->getLastTreatment($tmpOldContract->id);
                $tmp_old_contract_id = $tmpOldContract->old_contract_id;
            } else {
                break;
            }

            if ($i >= 4) {
                break;
            }
        }
        return $lastTreatment;
    }

    // 過去契約も含めた前回の予約取得
    public function getLastReservationWithOld(Contract $contractObject) {
        $lastReservation = $this->reservationModel->getLastReservation($contractObject->id);
        // プラン移行後の初回用に過去の契約も参照する
        $tmp_old_contract_id = $contractObject->old_contract_id;
        for ($i = 1; empty($lastReservation); $i++) {
            if ($tmp_old_contract_id !== 0) {
                $tmpOldContract = $this->contractModel->getContract($tmp_old_contract_id);
                if (empty($tmpOldContract)) {
                    // エラーレスポンス返却
                    throw new MyApiException(400, "E5002");
                }
                $lastReservation = $this->reservationModel->getLastReservation($tmpOldContract->id);
                $tmp_old_contract_id = $tmpOldContract->old_contract_id;
            } else {
                break;
            }

            if ($i >= 4) {
                break;
            }
        }
        return $lastReservation;
    }
}