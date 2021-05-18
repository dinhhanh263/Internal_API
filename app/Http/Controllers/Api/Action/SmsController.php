<?php
namespace app\Http\Controllers\Api\Action;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Service\SmsService;
use App\Service\UtilService;
use App\Model\Message\MailTemplate;
use App\Model\Message\MailSendResult;
use App\Model\Message\MailAuthority;

class SmsController extends Controller
{
    /**
     * SMS配信情報を受取りSMS転送エージェントAPIを呼び出すことでSMS配信を行う
     */
    public function send(Request $request, SmsService $smsService, MailTemplate $mailTemplateModel,
        MailSendResult $mailSendResultModel, MailAuthority $mailAuthorityModel) {
        // 共通smsバリデーション
        $validator = $this->smsCommonValid($request);
        // param以外のバリデーションエラー時処理
        if ($validator->fails()) {
            foreach ($validator->errors()->getMessages() as $key => $value) {
                // エラーレスポンス返却
                return response()->json([
                    'apiStatus' => 400,
                    'errorReasonCd' => $value[0],
                    'errorKey' => $key
                ]);
                break;
            }
        }

        // テンプレート取得
        $templateResult = $mailTemplateModel->getSmsTemplateText($validator->getData()['templateId']);
        if ($this->templateValid($templateResult) === false) {
            // エラーレスポンス返却
            return response()->json([
                'apiStatus' => 400,
                'errorReasonCd' => "E5002",
                'errorKey' => 'templateId'
            ]);
        }

        // 個別宛先以外のバリデーションチェック済みparamデータ
        $requestParams = $validator->getData()['param'];

        // エラー(リクエストが10000件を超える場合)
        if (count($requestParams) > 10000) {
            // エラーレスポンス返却
            return response()->json([
                'apiStatus' => 400,
                'errorReasonCd' => "E9008",
                'errorKey' => 'param'
            ]);
        }

        // paramバリデーションチェック
        list($sendBody, $failBody) = $smsService->paramValid($requestParams, $validator->getData()['sendParamType'], $validator->getData()['mailStatus'], $templateResult['text']);

        // エラー(送信先0件)
        if (empty($sendBody)) {
            // エラーレスポンス返却
            return response()->json([
                'apiStatus' => 400,
                'requestId' => null,
                'body' => $failBody
            ]);
        }

        // フロント返却用リクエストID採番
        $requestId = substr((new \DateTime())->format("YmdHisv"), 0, 15);

        // エージェントにPOST
        $agentResponse = $smsService->sendPostRequest($templateResult['template_name'], $templateResult['text'], $templateResult['url'], $sendBody, $requestParams, $requestId, $templateResult['group_cd'], $templateResult['email']);

        if ($agentResponse->getStatusCode() !== 200) {
            $errorAgentResponse = json_decode($agentResponse->getBody()->__toString(), true);
            if (!empty($errorAgentResponse['details'][0]['code'])) {
                $errorAgentResponseCode = $errorAgentResponse['details'][0]['code'];
            } else {
                $errorAgentResponseCode = $errorAgentResponse['code'];
            }

            // 結果履歴テーブルにインサート
            $mailSendResultModel->insert((int)$requestId, null, 1, $validator->getData()['templateId'],
                $templateResult['text'], null, 0, $errorAgentResponse['message'] ?: null);

            return response()->json([
                'apiStatus' => 400,
                'errorReasonCd' => $errorAgentResponseCode,
                'errorKey' => null
            ]);
        } else {
            $agentResponseBody = json_decode($agentResponse->getBody()->__toString(), true);

            // 結果履歴テーブルにインサート
            $mailSendResultModel->insert((int)$requestId, $agentResponseBody['delivery_id'], 0, $validator->getData()['templateId'],
                $templateResult['text'], $failBody ? json_encode($failBody) : null, count($sendBody), null);
        }

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'requestId' => $requestId,
            'body' => $failBody
        ], 200, [], JSON_FORCE_OBJECT);
    }

    /**
     * SMS配信情報を受取り事前バリデーションチェックを行う
     */
    public function checkValid(Request $request, SmsService $smsService, MailTemplate $mailTemplateModel) {
        // 共通smsバリデーション
        $validator = $this->smsCommonValid($request);
        // param以外のバリデーションエラー時処理
        if ($validator->fails()) {
            foreach ($validator->errors()->getMessages() as $key => $value) {
                // エラーレスポンス返却
                return response()->json([
                    'apiStatus' => 400,
                    'errorReasonCd' => $value[0],
                    'errorKey' => $key
                ]);
                break;
            }
        }

        // テンプレート取得
        $templateResult = $mailTemplateModel->getSmsTemplateText($validator->getData()['templateId']);
        if ($this->templateValid($templateResult) === false) {
            // エラーレスポンス返却
            return response()->json([
                'apiStatus' => 400,
                'errorReasonCd' => "E5002",
                'errorKey' => 'templateId'
            ]);
        }

        // 個別宛先以外のバリデーションチェック済みparamデータ
        $requestParams = $validator->getData()['param'];

        // エラー(リクエストが10000件を超える場合)
        if (count($requestParams) > 10000) {
            // エラーレスポンス返却
            return response()->json([
                'apiStatus' => 400,
                'errorReasonCd' => "E9008",
                'errorKey' => 'param'
            ]);
        }

        list($sendBody, $failBody) = $smsService->paramValid($requestParams, $validator->getData()['sendParamType'], $validator->getData()['mailStatus'], $templateResult['text']);

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'body' => $failBody
        ], 200, [], JSON_FORCE_OBJECT);
    }

    /**
     * 共通バリデーションチェック
     */
    private function smsCommonValid($request) {
        return Validator::make($request->all(), [
            'targetType' => 'required | string | in:"kireimo","kireimoStaff"',
            'sendParamType' => 'required | integer | min:1 | max:2',
            'mailStatus' => 'required | integer',
            'templateId' => 'required | integer',
            'param' => 'required',
            'param.*.customerNo' => 'string | required_if:sendParamType, 2',
            'param.*.toTel' => 'string | required_if:sendParamType, 1',
            'param.*.insert1' => 'string',
            'param.*.insert2' => 'string',
            'param.*.insert3' => 'string',
            'param.*.insert4' => 'string',
            'param.*.insert5' => 'string'
        ], UtilService::getValidateMessage());
    }

    /**
     * テンプレートバリデーションチェック
     */
    private function templateValid($templateResult) {
        if (empty($templateResult['text']) || mb_substr_count($templateResult['text'], '{{', "UTF-8") > config('myConfig.sms_insert_count')) {
            return false;
        } else {
            return true;
        }
    }

}

