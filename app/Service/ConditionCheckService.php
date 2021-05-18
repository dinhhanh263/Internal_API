<?php

namespace App\Service;

use App\Model\Kireimo\Basic;
use App\Model\Kireimo\Contract;
use App\Model\Kireimo\Course;
use App\Model\Kireimo\Customer;
use App\Model\Kireimo\Reservation;
use App\Exceptions\MyApiException;
use Carbon\Carbon;


class ConditionCheckService
{

    private $basicModel;
    private $reservationModel;
    private $contractModel;
    private $courseModel;

    public function __construct(Basic $basicModel, Reservation $reservationModel, Contract $contractModel, Course $courseModel)
    {
        $this->basicModel = $basicModel;
        $this->reservationModel = $reservationModel;
        $this->contractModel = $contractModel;
        $this->courseModel = $courseModel;
    }


    /**
     * 不正コンディション取得
     *
     * @param Customer $customerObject 顧客オブジェクト
     * @param Contract $contractObject 契約オブジェクト
     * @param Course $courseObject コースオブジェクト
     *
     * @return array
     */
    public function getFailedConditions (Customer $customerObject, Contract $contractObject, Course $courseObject) {
        // 不正コンディション一覧
        $failedConditions = null;

        // 契約ステータスチェック
        if ($contractObject->status !== 0) {
            $failedConditions[] = "BAD_CONTRACT_STASUS";
        }

        // 会員タイプチェック
        if ($customerObject->ctype !== 1 && $customerObject->ctype !== 101) {
            $failedConditions[] = "BAD_CUSTOMER_TYPE";
        }

        // 売掛金有無チェック
        if ($contractObject->balance > 0) {
            $failedConditions[] = "BAD_BALANCE_CONDITION";
        }

        // ローンステータスチェック(ローン延滞者、 承認中 && ローン支払あり、 クレピコ の場合予約不可)
        if ($contractObject->loan_delay_flg !== 0) {
            $failedConditions[] = "LOAN_STATUS_DELAY";
        } elseif ($contractObject->loan_status === 3 && $contractObject->payment_loan > 0) {
            $failedConditions[] = "LOAN_STATUS_APPOROVING";
        } elseif ($contractObject->loan_status === 5) {
            $failedConditions[] = "LOAN_STATUS_CREPICO";
        }

        // 契約時未成年者の親権者同意書提出済みチェック
        if (UtilService::getAgeByTargetDate($customerObject->birthday, $contractObject->contract_date) < 20
            && $customerObject->agree_status !== 1 && $contractObject->contract_date > "2019-12-31") {
            $failedConditions[] = "BAD_AGREE_STATUS";
        }

        // つなぎチェック(月額からパックにプラン変更後、消化がない人は予約不可)(脱毛のみ)
        if ($courseObject->treatment_type === 0 && $contractObject->r_times === 0 && $courseObject->type === 0 && $contractObject->old_contract_id !== 0) {
            // 旧契約情報取得
            $oldContractResult = $this->contractModel->getContract($contractObject->old_contract_id);
            if (empty($oldContractResult)) {
                // エラーレスポンス返却
                throw new MyApiException(400, "E5002");
            }
            // 旧コース情報取得
            $oldCourseResult = $this->courseModel->getCourse($oldContractResult->course_id);
            if ($oldCourseResult->type === 1) { // 旧コースが月額
                $failedConditions[] = "BAD_CONVERSION_CONDITION";
            }
        }

        // end_dateチェック(返金保証回数終了後の無制限プランは除く) (現在日付を基準にする)
        if ($this->checkContractEndDate($contractObject, $courseObject, date('Y-m-d')) === false) {
            $failedConditions[] = "CONVERSION_PERIOD_OVER";
        }

        // 回数チェック(消化回数>=コース回数の場合は予約できない(パックの場合))(返金保証回数終了後の無制限プランは除く)
        if ($courseObject->type === 0 && $contractObject->r_times >= $contractObject->times && $courseObject->times != 0) {
            $failedConditions[] = "CONVERSION_TIMES_OVER";
        }

        // クーリングオフチェック(契約日+8日未満の場合エラーを返す)(返金保証回数終了後、U-19からの移行後を除く)
        if ((new \DateTime(date('Y-m-d'))) < $this->getCoolingOffNextDate($contractObject->contract_date)
            && empty($courseObject->old_course_id) && !($contractObject->old_course_id == 102 && $courseObject->id == 92)
            && $courseObject->id < 999) {
                $failedConditions[] = "COOLING_OFF_TIME";
        }

        // 月額U-19プラン用最終消化済みチェック
        if ($courseObject->minor_plan_flg === 1 && !empty($contractObject->end_date)
            && Carbon::parse($contractObject->end_date)->firstOfMonth()->addMonth(-1)->toDateString() <= $contractObject->latest_date) {
            $failedConditions[] = "MINOR_PLAN_OVER";
        }

        return $failedConditions;
    }

    // end_dateチェック(返金保証回数終了後の無制限プランは除く)
    public function checkContractEndDate($contractObject, $courseObject, string $date) {
        if ($contractObject->end_date !== "0000-00-00" && $contractObject->end_date < $date && $courseObject->times != 0 && $courseObject->zero_flg == 0) {
            if (empty($contractObject->extension_end_date) || $date > $contractObject->extension_end_date ) {
                return false;
            }
        }
        return true;
    }

    public function getBaseDate(Contract $contractObject, Course $courseObject, Reservation $lastCancelPenalty = null, string $lastTreatmentHopeDate) {
        // 0:脱毛, 1:エステ, 2:整体
        if ($courseObject->treatment_type === 0) {
            $baseDate = "";

            // 契約コースのタイプが月額の場合(新月額の場合)
            if($courseObject->type !=0){
                $currentTermYm = $this->getCurrentTermYm($contractObject->start_ym);

                // キャンセル(ペナルティ消化)がない場合
                if(empty($lastCancelPenalty)){
                    // 初期表示時の日付の開始日の取得
                    $baseDate = date("Y-m-d", $this->getBaseDateCalc($courseObject->type, $lastTreatmentHopeDate, $contractObject->contract_date, false, $currentTermYm,"",""));

                } else {
                    // キャンセル(ペナルティ消化)がある場合
                    // ※ $cancelIdが旧契約コースのIDを取ってきてしまうため、暫定的に下記の分岐を作成しています。 2017/04/14 modify by shimada

                    // 「最終お手入れ日」<「ペナルティ消化日」であるとき && 「ペナルティ消化時の契約ID」==「現在の契約ID」のとき 2017/04/13 modify by shimada
                    // ペナルティ消化日が現在のターム内にあるかどうかをgetBaseDateCalcの処理で判定しています。
                    if($lastTreatmentHopeDate < $lastCancelPenalty->hope_date && $contractObject->id == $lastCancelPenalty->contract_id){
                        // 「ペナルティ消化あり」として次の予約開始日を取得する
                        $baseDate = date("Y-m-d", $this->getBaseDateCalc($courseObject->type, $lastCancelPenalty->hope_date, $contractObject->contract_date,true,$currentTermYm,$lastCancelPenalty->sales_id,""));
                        // 「ペナルティ消化日」<「最終お手入れ日」のとき、もしくは「最終お手入れ日が存在しないとき」
                    } else {
                        // 最終お手入れ日が存在する場合
                        if($lastTreatmentHopeDate){
                            // 「最終お手入れ日」から次の予約開始日を取得する
                            $baseDate = date("Y-m-d", $this->getBaseDateCalc($courseObject->type, $lastTreatmentHopeDate, $contractObject->contract_date,false,$currentTermYm,"",""));
                            // 最終お手入れ日が存在しない場合
                        } else {
                            // 「新規」の予約開始日を取得する
                            $baseDate = date("Y-m-d", $this->getBaseDateCalc($courseObject->type, "", $contractObject->contract_date,false,$currentTermYm,"",""));
                        }
                    }
                }

            }else{ // パック
                if ($courseObject->sales_start_date >= "2019-11-06" || $courseObject->id >= 1005) {
                    // 販売開始日が2019-11-06以降 latest_dateとr_timesを見るだけにし実際に施術していなくても周期を開ける
                    $baseDate = date("Y-m-d", $this->getBaseDateCalc($contractObject->course_type, $contractObject->latest_date, $contractObject->contract_date,false,null,"",$contractObject->r_times, $courseObject));
                } elseif ($courseObject->id == 1001 || $courseObject->id == 1002 || $courseObject->id == 1004) {
                    // 返金保証回数終了後は周期にキャンセルを含める

                    // 過去契約も含めた最終消化取得
                    $LastTreatmentOrPenalty = $this->reservationModel->getLastTreatmentOrPenalty($contractObject->id);
                    $tmp_old_contract_id = $contractObject->old_contract_id; // プラン移行後の初回用に過去の契約も参照する
                    for ($i = 1; empty($LastTreatmentOrPenalty); $i++) {
                        if ($tmp_old_contract_id !== 0) {
                            $tmpOldContract = $this->contractModel->getContract($tmp_old_contract_id);
                            if (empty($tmpOldContract)) {
                                // エラーレスポンス返却
                                throw new MyApiException(400, "E5002");
                            }
                            $LastTreatmentOrPenalty = $this->reservationModel->getLastTreatmentOrPenalty($tmpOldContract->id);
                            $tmp_old_contract_id = $tmpOldContract->old_contract_id;
                        } else {
                            break;
                        }

                        if ($i >= 4) {
                            break;
                        }
                    }
                    $baseDate = date("Y-m-d", $this->getBaseDateCalc($contractObject->course_type, $LastTreatmentOrPenalty->hope_date ?? null, $contractObject->contract_date,false,null,"",$contractObject->r_times, $courseObject));
                } else {
                    $baseDate = date("Y-m-d", $this->getBaseDateCalc($contractObject->course_type, $lastTreatmentHopeDate, $contractObject->contract_date,false,null,"",$contractObject->r_times, $courseObject));
                }
            }
        } else { // エステの場合
            $baseDate = date("Y-m-d");
        }

        return $baseDate;
    }

    public function getMaxDate(Contract $contractObject, Course $courseObject, string $baseDate, string $lastTreatmentHopeDate, Reservation $lastCancelPenalty = null) {

        // 脱毛新月額の場合
        if ($courseObject->treatment_type === 0 && $courseObject->type === 1) {
            // 現在のターム取得
            $currentTermYm = $this->getCurrentTermYm($contractObject->start_ym);

            // 新規契約後、最初の予約
            if (empty($lastTreatmentHopeDate) && empty($lastCancelPenalty)) {
                // 最終お手入れ日（reserv）と最終お手入れ日（cancel）の両方が存在しない時だけ以下のチェック処理を実行
                // 施術開始年月と当日の差が偶数か奇数かチェック
                $oddEvenFlg = $this->check_OddEven($currentTermYm);
                if ($oddEvenFlg == true) {
                    // 偶数の場合
                    $maxDate = date("Y-m-d", $this->getMonthMaxDateCalc($baseDate,$contractObject->end_date,""));
                } else {
                    // 奇数の場合
                    // 算出済みの予約開始日が施術開始年月から翌月末日の範囲内か調べる
                    $twoMonthFlg = $this->check_TwoMonth($currentTermYm, $baseDate);
                    $maxDate = date("Y-m-d", $this->getMonthMaxDateCalc($baseDate,$contractObject->end_date,$twoMonthFlg));
                }

            } else { // 最終お手入れ日やキャンセル消化日が取得できる場合

                // 次の予約が1か月目か2か月目か判定するために処理を分岐 2017/02/21 add by shimada
                // 最終お手入れ日（reserv）と最終お手入れ日（cancel）の両方が存在しない時だけ以下のチェック処理を実行
                // 施術開始年月と当日の差が偶数か奇数かチェック
                $oddEvenFlg = $this->check_OddEven($currentTermYm);
                if ($oddEvenFlg == true) {
                    // 偶数の場合
                    $maxDate = date("Y-m-d", $this->getMonthMaxDateCalc($baseDate,$contractObject->end_date,""));
                } else {
                    // 奇数の場合
                    // 算出済みの予約開始日が施術開始年月から翌月末日の範囲内か調べる
                    $twoMonthFlg = $this->check_TwoMonth($currentTermYm, $baseDate);
                    $maxDate = date("Y-m-d", $this->getMonthMaxDateCalc($baseDate,$contractObject->end_date,$twoMonthFlg));
                }
            }
        } else {
            // パック、エステの場合
            // 現在日を基準日にする
            $baseDate = date("Y-m-d");

            if ($courseObject->treatment_type === 1) {
                // エステは28日
                $maxDate = (new \DateTime($baseDate))->modify('+28 day');
            } else {
                // パックは180日
                $maxDate = (new \DateTime($baseDate))->modify('+180 day');
            }
            if ($contractObject->times != 0) { // 返金保証回数終了後のSP以外は契約終了日を考慮
                $contractEndDate = $contractObject->extension_end_date ?? $contractObject->end_date;
                $contractEndDate = new \DateTime($contractEndDate);
                // 120日後(エステの場合は28日後)より契約終了日のほうが前の場合は契約終了日を設定
                if ($contractEndDate < $maxDate) {
                    $maxDate = $contractEndDate;
                }
            }

            $maxDate = $maxDate->format('Y-m-d');
        }

        return $maxDate;
    }

    /**
     * 基準日付の取得(脱毛トリートメント)
     *
     * @param int $type タイプ (0.パック 1.月額)
     * @param string $lastDate 最終お手入れ日（旧契約関係なし）
     * @param string $regDate 登録日時
     * @param int $startYm 現在日ターム年月
     * @param int $salesId 売上ID(ペナルティ判別用)
     * @param int $Rtimes 消化回数(contract.r_times)
     *
     * @return int
     */
    private function getBaseDateCalc($type,$lastDate = null,$regDate,$cancelFlg,$startYm = null,$salesId,$Rtimes=0,$courseResult = null) {
        // 現タームの初日の設定
        $dFirstYm = date("Y-m-d",strtotime($startYm."01")); // date型に変換
        $lastDate = $lastDate === "0000-00-00" ? null : $lastDate;
        // 月額制で新月額の場合
        if ($type == 1) {

            $firstYm = strtotime($startYm."01"); // 現タームの初日
            // 最終お手入れ日(予約・ペナルティ消化)が取得できない場合
            if(empty($lastDate)) {

                // 現タームの初日から予約をできるようにする
                $baseDate = $firstYm;

            } else { // 最終お手入れ日(予約・ペナルティ消化)が取得できる場合

                // ペナルティを受けている場合
                if ($cancelFlg == true) {
                    // 最終お手入れ日 < 契約日の場合、現タームの初日をセットする
                    if ($lastDate < $regDate) {
                        $baseDate = $firstYm;

                    } else { // 最終お手入れ日 < 契約日ではない場合

                        // new_search.php * change_search.phpの処理を埋め込み 2017/04/14 add by shimada
                        // 前々回以下のタムにキャンセル消化があり、前回のタムにキャンセル消化がない場合
                        if($this->month_diff(strtotime($lastDate), strtotime($startYm."01"))<0 ){
                            // 当日を設定
                            $baseDate = strtotime("0day");
                            // 施術開始年月：1ヶ月目(予約実行当日の月、今回の施術開始年月差が「0」)
                        } else if($this->month_diff(strtotime(date('Y-m-d')), strtotime($startYm."01"))==0){
                            // 当日の翌々月の月初日を設定
                            $tBaseDate = strtotime("+1 month",strtotime("0day"));
                            $dBaseDate = date("Y-m-t",$tBaseDate);
                            $tTBaseDate = strtotime("+1 day",strtotime($dBaseDate));
                            $baseDate = $tTBaseDate;
                            // 施術開始年月：2ヶ月目(予約実行当日の月、今回の施術開始年月差が「1」)
                        } else if($this->month_diff(strtotime(date('Y-m-d')), strtotime($startYm."01"))==1){
                            // 予約する月とペナルティ消化月を比べて、次に予約可能な月を算出する 2017/06/23 add by shimada
                            // ペナルティ消化年月と本日の年月が同じ
                            if(date('Ym', strtotime($lastDate))==date('Ym')){
                                $cancelEndOfmonth = "0day";
                                // ペナルティ消化年月と本日の年月が異なる
                                // ※たとえば施術開始年月：2017/05～の人が、2017/6/30に2017/7/1のペナルティ消化をした場合、次のタームは2017/09～2017/10月となる
                            } else {
                                $cancelEndOfmonth = date('Y-m-01', strtotime($lastDate));
                                $cancelEndOfmonth = date("Y-m-01",strtotime($cancelEndOfmonth . "+1 month"));
                            }

                            // 当日の翌月 OR ペナルティ消化した次のタームの月初日を設定
                            $dBaseDate = date("Y-m-t",strtotime($cancelEndOfmonth));
                            $tTBaseDate = strtotime("+1 day",strtotime($dBaseDate));
                            $baseDate = $tTBaseDate;
                        }
                    }

                } else { // ペナルティを受けていない場合

                    // 現タームの初日から1ヶ月後の末日を求める
                    $tNextMonth = strtotime("+1 month",$firstYm);    // 翌月月初日(タイムスタンプ)
                    $dNextMonth = date("Y-m-t",$tNextMonth);         // 翌月末をdate型に変換
                    $nextMonth = strtotime($dNextMonth);             // 翌月末日(タイムスタンプ)

                    // 現タームの初日 <= 最終お手入れ日 の場合(現タームで消化済みの場合)
                    if ($dFirstYm <= $lastDate){
                        // 次タームの初日を設定
                        $baseDate = strtotime("+1 day",$nextMonth);

                    } else { // 最終消化が現タームより前の場合

                        // 現タームの初日を設定
                        $baseDate = $firstYm;
                    }

                    // 最終お手入れ日 < 契約日の場合、施術開始年月の初日をセットする(再契約時の対応)　2017/04/08 add by shimada
                    //　※藤城さんからテストのエラー報告があったので、ここのコメントアウトを戻しました。
                    // 下記の「$lastDate < $regDate」→「$lastDate <= $regDate」に修正 2017/05/30 add by shimada
                    // ※旧コースの最終お手入れ日と新コースの契約日が同じ場合も分岐に入れないとタームがずれてしまうための修正です。
                    if ($lastDate <= $regDate) {
                        // 現タームの初日をセットする
                        $baseDate = $firstYm;
                    }
                }
            }

        } elseif ($type == 0) { // パックの場合
            // 回数制でパック(お手入れ日なし、初回予約)
            if(empty($lastDate)) {
                $baseDate = strtotime("0day");

                // 回数制でパック(お手入れ日あり)
            } else {
                $baseDate = strtotime($lastDate);
                // 次の施術可能日 2017/01/18 shimada
                $baseDate = $this->datePossibleByTreatment($baseDate,$regDate,$Rtimes,$courseResult);
            }
        }

        // 【共通】算出した予約可能開始日と当日を比較し当日よりも前の場合は当日に置き換える
        if ($baseDate < strtotime("0day")) {
            $baseDate = strtotime("0day");
        }

        return $baseDate;
    }

    /**
     * 次の施術可能な日にちを計算する(脱毛トリートメント)
     *
     * @param string $baseDate 基準日
     * @param string $contract_date 契約日(contract.contract_date)
     * @param int $r_times 消化回数(contract.r_times)
     *
     * @return int
     */
    private function datePossibleByTreatment($baseDate,$contractDate,$Rtimes, $courseResult = null) {
        // n回目の判定用
        $minus = 1;
        // 消化回数 1回目～
        if(0 -$minus < $Rtimes){
            // 2020/10/01以降のSPプラン
            if ($courseResult->sales_start_date >= "2020-10-01" && $courseResult->zero_flg == 1) {
                if(1-$minus<= $Rtimes && $Rtimes <= 12-$minus){
                    // 1回目～12回目まで
                    $possibleDate = strtotime("45day",$baseDate); // 45日間隔
                } elseif(13-$minus<= $Rtimes){
                    // 13回目～
                    $possibleDate = strtotime("60day",$baseDate); // 60日間隔
                }
                // 2019/11/06以降の販売コース または 無料一回コース
            }elseif (($courseResult->sales_start_date >= "2019-11-06" && $courseResult->interval_date !== null)
                || $courseResult->group_id == 80 && $courseResult->interval_date !== null ) {
                $possibleDate = strtotime($courseResult->interval_date."day",$baseDate);
            } else {
                // 契約日が2017/01/25以降～
                if("2017-01-25" <= $contractDate){
                    // 1回目～6回目まで
                    if(1-$minus<= $Rtimes && $Rtimes <= 6-$minus){
                        $possibleDate = strtotime("60day",$baseDate); // 60日間隔
                        // 7回目～12回目まで
                    } elseif(7-$minus<= $Rtimes && $Rtimes <= 12-$minus){
                        $possibleDate = strtotime("75day",$baseDate); // 75日間隔
                        // 13回目以降～(通いホーダイの18回目以降も考慮)
                    } elseif(13-$minus<= $Rtimes){
                        $possibleDate = strtotime("90day",$baseDate); // 90日間隔
                    }

                        // 契約日が2017/01/24以前
                        // elseifの7回目～、13回目～はマイページに注意文言を掲載してから再度コメントアウト期間を設定する
                } else {
                    // 1回目～6回目まで
                    if(1-$minus<= $Rtimes && $Rtimes <= 6-$minus){
                        $possibleDate = strtotime("45day",$baseDate); // 45日間隔
                        // 7回目～12回目まで
                    } elseif(7-$minus<= $Rtimes && $Rtimes <= 12-$minus){
                        // $possibleDate = strtotime("75day",$baseDate); // 75日間隔
                        $possibleDate = strtotime("45day",$baseDate); // 45日間隔
                        // 13回目以降～(通いホーダイの18回目以降も考慮)
                        } elseif(13-$minus<= $Rtimes){
                            // $possibleDate = strtotime("90day",$baseDate); // 90日間隔
                            $possibleDate = strtotime("45day",$baseDate); // 45日間隔
                        }
                }
            }

        } else {
            $possibleDate = $baseDate;
        }
        return $possibleDate;
    }

    /**
     * 期間チェック
     *
     * @param string $limitDay リミット日
     * @param string $targetDate 対象日
     *
     * @return int
     */
    private function check_HopeDate($targetDate, $limitDay) {
        if (($targetDate <= $limitDay)) {
            // 最終お手入れ日の2週間後が施術開始年月の初日内である場合
            $result = $limitDay;
        } else {
            // メソッドで求めた予約日を越えてしまう場合は2週間後（14日）する
            $result = $targetDate;
        }
        return $result;
    }

    /**
     * 現在のターム年月を取得
     *
     * @param int $startYm 施術開始年月
     *
     * @return int
     */
    private function getCurrentTermYm($startYm) {
        // 比較月が違う年でも対応　20170116 ka
        $today=strtotime("0day");
        $date2=strtotime($startYm."01");
        $diff = $this->month_diff($today,$date2);
        // 先月(当日が31日で前月が31日ないと先月の日付を取れないため、下記を記載。) 2017/03/31 add by shimada
        $lastmonth = date('Ymd', strtotime(date("Ym01") . ' -1 month'));

        if($diff>0){
            if ($diff % 2 == 0){
                // 偶数の場合
                $currentYm = date("Ym");
            } else {
                // 奇数の場合
                // $currentYm = date('Ym', strtotime('-1 month')); //　先月を正しく取れないためコメントアウト
                $currentYm = date('Ym', strtotime($lastmonth));
            }
        } else {
            $currentYm = $startYm;
        }
        return $currentYm;
    }

    /**
     * 月の差を求める
     *
     * @param int $date1
     * @param int $date2
     *
     * @return int
     */
    private function month_diff($date1,$date2){
        $month1=date("Y",$date1)*12+date("m",$date1);
        $month2=date("Y",$date2)*12+date("m",$date2);

        $diff = $month1 - $month2;
        return $diff;
    }

    // 施術開始年月と予約実施当日の月を比較し奇数か偶数かチェック
    // $startYm		施術開始年月
    private function check_OddEven($startYm) {
        // 比較月が違う年でも対応　20170116 ka
        $today=strtotime("0day");
        $date2=strtotime($startYm."01");
        $diff = $this->month_diff($today,$date2);

        if($diff>0){
            if ($diff % 2 == 0){
                // 偶数の場合
                return true;
            } else {
                // 奇数の場合
                return false;
            }
        } else {
            return true;
        }
    }

    // 予約開始日が施術開始年月の月から翌月の範囲内かチェック
    // $startYm		施術開始年月
    // $baseDate		予約開始日
    private function check_TwoMonth($startYm,$baseDate) {
        // 施術開始日年月の月初日の設定
        $firstYm = strtotime($startYm."01");
        // 施術開始年月から1ヶ月後の末日を求める
        $tNextMonth = strtotime("+1 month",$firstYm);
        $dNextMonth = date("Y-m-t",$tNextMonth);
        $nextMonth = strtotime($dNextMonth);
        // 比較用に予約開始日をタイムスタンプ型に変換
        $diffBaseDate = strtotime($baseDate);
        // 当月契約新規、スタート年月が翌月の場合
        if( ($firstYm == $diffBaseDate) && ($nextMonth > $diffBaseDate)){
            return false;
        } elseif (($firstYm <= $diffBaseDate) && ($nextMonth >= $diffBaseDate)){
            // 施術開始年月から翌月末の範囲内に予約開始日がある場合
            return true;
        } else {
            // 範囲外の場合
            return false;
        }
    }

    // 月額の選択可能最大日付の取得
    // $baseDate	基準日付
    // $endDate	契約期間
    // $twoMonthFlg 範囲チェック後結果  //2016/10/1 jid kaneko
    private function getMonthMaxDateCalc($baseDate,$endDate,$twoMonthFlg) {
        // 月額の検索期間を取得
        $kikan = $this->getMonthKikan($baseDate,$twoMonthFlg);
        // 期間（単位：月）を日数に置き換えて計算し最大日を求める
        $total = 0;
        $times = 0;
        $day = 0;
        for ($i = 0; $i < $kikan; $i++) {
            $monthDate = $this->getChangeDate($total." day", $baseDate);
            $day = date("j",$monthDate);
            $times = date("t",$monthDate);
            $total = $total + ($times - $day) + 1;
        }
        $maxDate = $this->getChangeDate(($total - 1)." day",$baseDate);

        list($Y, $m, $d) = explode('-', $endDate);

        if (checkdate($m, $d, $Y) == true){
            // 契約テーブルの契約期間が存在する日付の場合
            if ($maxDate > strtotime($endDate)) {
                // 契約期間より後の日付は設定できないようにする
                $maxDate = strtotime($endDate);
            }
        }

        return $maxDate;
    }

    // 引数の変更値による日付の取得
    // $arg			strtotimeへの引数
    // $selectDate	変更前日付
    private function getChangeDate($arg,$selectDate) {
        $changeDate = strtotime($selectDate);
        $changeDate = strtotime($arg,$changeDate);

        return $changeDate;
    }


    // 月額の検索期間を取得
    // $baseDate	基準日付
    // $twoMonthFlg 範囲チェック後結果  //2016/10/1 jid kaneko
    private function getMonthKikan($baseDate,$twoMonthFlg) {
        if ($twoMonthFlg == true) {
            // 算出済みの予約開始日が施術開始年月と翌月末の範囲内である時
            $kikan = 1;
        } else {
            // 範囲外または新月額以外（空白）の場合
            $kikan = 2;
        }
        return $kikan;
    }

    /**
     * // クーリングオフ期間(8日)を加算した日付を返す
     *
     * @param string $date 日付
     *
     * @return \DateTime
     */
    private function getCoolingOffNextDate(string $date) {
        $coolingOffDays = (int)$this->basicModel->getBasic(4)->value;
        return (new \DateTime($date))->modify("+$coolingOffDays day");
    }

}