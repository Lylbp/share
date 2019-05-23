<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 2019/4/28
 * Time: 下午4:51
 */

namespace share\enum;


use thl\enum\ThlResultEnum;

class ShareResultEnum extends ThlResultEnum
{
    //微信相关
    const WECHART_NO_ACCESS_TOKEN_CODE = "201";
    const WECHART_NO_ACCESS_TOKEN_MSG = "access_token未获取";

    public function __construct($value)
    {
        parent::__construct($value);
    }

}