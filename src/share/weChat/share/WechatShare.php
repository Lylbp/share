<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 2019/4/29
 * Time: 下午3:49
 */

namespace share\share\weChat\share;

use share\enum\ShareResultEnum;
use share\Exception\ShareException;
use share\share\weChat\abstracts\WeChatConfigAbstract;
use share\share\weChat\interfaces\WeChatShareInterface;
use thl\common\CurlTool;
use thl\common\ResultUtil;
use thl\common\StringTool;
use thl\common\YmlTool;
use thl\entity\ThlResult;
use thl\enum\ThlRandomTypeEnum;
use thl\enum\ThlResultEnum;
use thl\exception\ThlResultException;
use thl\ThlBase;

class WechatShare extends ThlBase implements WeChatShareInterface
{

    /**
     * @var WeChatConfigAbstract null
     */
    protected static $weChatConfig = null;

    /**
     * @var null
     */
    protected static $wechatUrlConfig = null;

    /**
     * WechatShare constructor.
     * @param $weChatConfig
     * @throws ShareException
     * @throws ThlResultException
     */
    public function __construct($weChatConfig)
    {
        parent::__construct();
        if (empty(self::$weChatConfig)){
            self::$weChatConfig = $weChatConfig;
        }

        self::$wechatUrlConfig = YmlTool::getParameters("wechat","share/config/shareUrlConfig.yml");
        if (empty(self::$wechatUrlConfig)){
            throw new ShareException(
                ThlResultEnum::PARAM_PARSE_ERROR_CODE,
                ThlResultEnum::PARAM_PARSE_ERROR_MSG
            );
        }
    }

    /**
     * @return mixed|ThlResult
     */
    public function getAccessToken(){
        $app_id = self::$weChatConfig->getWechatAppId();
        $secret = self::$weChatConfig->getWechatAppSecret();

        if (!isset(self::$wechatUrlConfig['get_access_token_url'])){
            return new ThlResult(
                ThlResultEnum::PARAM_PARSE_ERROR_CODE,
                ThlResultEnum::PARAM_PARSE_ERROR_MSG
            );
        }

        $url =  self::$wechatUrlConfig['get_access_token_url'];
        $res = CurlTool::httpGet($url."?grant_type=client_credential&appid={$app_id}&secret={$secret}");
        $access_token_res = json_decode($res, true);

        return $this->returnRewrite($access_token_res);
    }

    /**
     * @param $access_token
     * @return mixed|ThlResult
     */
    public function getTicket($access_token)
    {
        if (!isset(self::$wechatUrlConfig['get_ticket_url'])){
            return new ThlResult(
                ThlResultEnum::PARAM_PARSE_ERROR_CODE,
                ThlResultEnum::PARAM_PARSE_ERROR_MSG
            );
        }

        $url = self::$wechatUrlConfig['get_ticket_url'];
        $res = CurlTool::httpGet($url."?access_token={$access_token}&type=jsapi");
        $ticket = json_decode($res, true);

        return $this->returnRewrite($ticket);;
    }

    /**
     * @param $url
     * @return mixed|ThlResult
     */
    public function shareParameter($url)
    {
        //用于分享的参数
        $time_stamp = time();
        $nonce_str = StringTool::generateRandom(ThlRandomTypeEnum::RANDOM_CAPTCHA,6);

        //获取access_token
        $access_token_res = $this->getAccessToken();
        if ($access_token_res->getCode() != ThlResultEnum::SUCCESS_CODE){
            return $access_token_res;
        }

        $access_token_data = $access_token_res->getData();
        if (!isset($access_token_data['access_token'])){
            return new ThlResult(
                ShareResultEnum::WECHART_NO_ACCESS_TOKEN_MSG,
                ShareResultEnum::WECHART_NO_ACCESS_TOKEN_CODE
            );
        }

        //获取临时票据
        $ticket_res = $this->getTicket($access_token_data['access_token']);
        if($ticket_res->getCode() != ThlResultEnum::SUCCESS_CODE){
            return $ticket_res;
        }

        $ticket_data = $ticket_res->getData();
        $string = "jsapi_ticket={$ticket_data['ticket']}&noncestr={$nonce_str}&timestamp={$time_stamp}&url=" . $url;

        $signature = sha1($string);

        $data = array(
            'url' => $url,
            'nonce_str' => $nonce_str,
            'time_stamp'=> $time_stamp,
            'app_id'=> self::$weChatConfig->getWechatAppId(),
            'signature'=> $signature
            );

        return new ThlResult(ThlResultEnum::SUCCESS_MSG,ThlResultEnum::SUCCESS_CODE,$data);
    }



    function returnRewrite($result)
    {
        if (empty($result) || !is_array($result)){
            return ResultUtil::error();
        }

        if(!array_key_exists('errcode',$result)){
            return ResultUtil::success($result);
        }

        $errCode = $result['errcode'];
        switch ($errCode){
            case 0:
                return ResultUtil::success($result);
            case -1:
                $message = '系统繁忙，此时请开发者稍候再试';
                break;

            case 40001:
                $message = '获取 access_token 时 AppSecret 错误，或者 access_token 无效。请开发者认真比对 AppSecret 的正确性，或查看是否正在为恰当的公众号调用接口';
                break;

            case 40002:
                $message = '不合法的凭证类型';
                break;

            case 40003:
                $message = '不合法的 OpenID ，请开发者确认 OpenID （该用户）是否已关注公众号，或是否是其他公众号的 OpenID';
                break;

            case 40013:
                $message = '不合法的 AppID ，请开发者检查 AppID 的正确性，避免异常字符，注意大小写';
                break;

            case 40014:
                $message = '不合法的 access_token ，请开发者认真比对 access_token 的有效性（如是否过期），或查看是否正在为恰当的公众号调用接口';
                break;

            case 41008:
                $message = '缺少oauth code';
                break;

            case 41009:
                $message = '缺少 openid';
                break;

            case 42001:
                $message = 'access_token 超时，请检查 access_token 的有效期，请参考基础支持 - 获取 access_token 中，对 access_token 的详细机制说明';
                break;

            case 42002:
                $message = 'refresh_token 超时';
                break;

            case 50005:
                $message = '用户未关注公众号';
                break;

            case 61451:
                $message = '参数错误 (invalid parameter)';
                break;

            case 9001006:
                $message = '获取 OpenID 失败';
                break;
            default:
                $message = '三方交互错误';
                break;
        }

        return new ThlResult(
            "message:{$message},WXerrcode:{$result['errcode']},WXErrmsg:{$result['errmsg']}",
            ThlResultEnum::ERROR_CODE
        );
    }
}