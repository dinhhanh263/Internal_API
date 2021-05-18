<?php
namespace app\Http\Controllers\Api\Action;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Service\MailService;
use App\Service\UtilService;
use App\Model\Message\MailTemplate;
use App\Model\Message\MailSendResult;
use App\Model\Message\MailAuthority;

class MailController extends Controller
{
    /**
     * メール配信情報を受取りメール配信エージェント(MTA)を呼び出すことでメール配信を行う
     */
    public function send(Request $request, MailService $mailService, MailTemplate $mailTemplateModel,
        MailSendResult $mailSendResultModel, MailAuthority $mailAuthorityModel) {
        // 共通メールバリデーション
        $validator = $this->mailCommonValid($request);
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
        $templateResult = $mailTemplateModel->getMailTemplateText($validator->getData()['templateId']);
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

        // paramバリデーションチェック
        list($sendBody, $failBody) = $mailService->paramValid($requestParams, $validator->getData()['sendParamType'], $validator->getData()['mailStatus'], $templateResult['text']);

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
        $mailService->sendPostRequest($templateResult['title'], $templateResult['text'], $sendBody, $validator->getData()['targetType'], $requestParams, $requestId);

        // 結果履歴テーブルにインサート
        $mailSendResultModel->insert((int)$requestId, null, 0, $validator->getData()['templateId'],
            $templateResult['text'], $failBody ? json_encode($failBody) : null, count($sendBody), null);

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'requestId' => $requestId,
            'body' => $failBody
        ], 200, [], JSON_FORCE_OBJECT);
    }

    /**
     * メール配信情報を受取り事前バリデーションチェックを行う
     */
    public function checkValid(Request $request, MailService $mailService, MailTemplate $mailTemplateModel) {
        // 共通メールバリデーション
        $validator = $this->mailCommonValid($request);
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
        $templateResult = $mailTemplateModel->getMailTemplateText($validator->getData()['templateId']);
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

        list($sendBody, $failBody) = $mailService->paramValid($requestParams, $validator->getData()['sendParamType'], $validator->getData()['mailStatus'], $templateResult['text']);

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'body' => $failBody
        ], 200, [], JSON_FORCE_OBJECT);
    }

    /**
     * 共通バリデーションチェック
     */
    private function mailCommonValid($request) {
        return Validator::make($request->all(), [
            'targetType' => 'required | string | in:"kireimo","kireimoStaff"',
            'sendParamType' => 'required | integer | min:1 | max:2',
            'mailStatus' => 'required | integer',
            'textMode' => 'required | string | in:"plane"',
            'templateId' => 'required | integer',
            'param' => 'required',
            'param.*.customerNo' => 'string | required_if:sendParamType, 2',
            'param.*.toAddress' => 'string | required_if:sendParamType, 1',
            'param.*.insert1' => 'string',
            'param.*.insert2' => 'string',
            'param.*.insert3' => 'string',
            'param.*.insert4' => 'string',
            'param.*.insert5' => 'string',
            'param.*.insert6' => 'string',
            'param.*.insert7' => 'string',
            'param.*.insert8' => 'string',
            'param.*.insert9' => 'string',
            'param.*.insert10' => 'string'
        ], UtilService::getValidateMessage());
    }

    /**
     * テンプレートバリデーションチェック
     */
    private function templateValid($templateResult) {
        if (empty($templateResult['title']) || empty($templateResult['text'])) {
            return false;
        } else {
            return true;
        }
    }
}