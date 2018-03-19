<?php
class Zou_Wxpay_Helper_Api extends Zou_Wxpay_Helper_Data
{
    protected $mchid;
    protected $appid;
    protected $appKey;
    protected $apiKey;
    public function http_post($url, $data)
    {
        if (! function_exists('curl_init')) {
            throw new Exception('php未安装curl组件', 500);
        }
        $protocol = (! empty ( $_SERVER ['HTTPS'] ) && $_SERVER ['HTTPS'] !== 'off' || $_SERVER ['SERVER_PORT'] == 443) ? "https://" : "http://";
        $website = $protocol.$_SERVER['HTTP_HOST'];
    
        $ch = curl_init();
        
        $url = $url . '?' .http_build_query($data);
        //file_put_contents(Mage::getBaseDir('media').'/zou.txt', $url.PHP_EOL,FILE_APPEND);
        //echo $url;
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_REFERER, $website);
        curl_setopt( $ch ,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        $response = curl_exec($ch);
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($httpStatusCode != 200) {
            throw new Exception("invalid httpstatus:{$httpStatusCode} ,response:$response,detail_error:" . $error, $httpStatusCode);
        }
        
        file_put_contents(Mage::getBaseDir('media').'/zou.txt', print_r($response,true).PHP_EOL,FILE_APPEND);
        return $response;
    }

        /**
     * curl get
     *
     * @param string $url
     * @param array $options
     * @return mixed
     */
    public function curlGet($url = '', $options = array())
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    public function curlPost($url = '', $postData = '', $hasCert = false, $options = array())
    {
        if (is_array($postData)) {
            $postData = http_build_query($postData);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //设置cURL允许执行的最长秒数
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        if($hasCert){
            curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,1);//证书检查
            curl_setopt($ch,CURLOPT_SSLCERTTYPE,'pem');
            curl_setopt($ch,CURLOPT_SSLCERT,dirname(__FILE__).'/cert/apiclient_cert.pem');
            curl_setopt($ch,CURLOPT_SSLCERTTYPE,'pem');
            curl_setopt($ch,CURLOPT_SSLKEY,dirname(__FILE__).'/cert/apiclient_key.pem');
            curl_setopt($ch,CURLOPT_SSLCERTTYPE,'pem');
            curl_setopt($ch,CURLOPT_CAINFO,dirname(__FILE__).'/cert/rootca.pem');
        }else{
            //https请求 不验证证书和host
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    /*
     * 二维码订单适合非微信客户端的支付场景使用。客户可通过手机微信扫描该 API 生成 的二维码进入微信支付界面进行支付。
     */
    public function makeQROrder($sendData){
        $wxpayApi = Mage::helper('wxpay')->getAppInfo();
        $totalFee = $sendData['amount'];
        $outTradeNo = $sendData['out_order_no'];
        $orderName = $sendData['order_name'];
        $notifyUrl = $sendData['notify_url'];
        $timestamp = $wxpayApi['timestamp'];
        //$orderName = iconv('GBK','UTF-8',$orderName);
        $unified = array(
            'appid' => $wxpayApi['app_id'],
            'attach' => 'wxpay',             //商家数据包，原样返回，如果填写中文，请注意转换为utf-8
            'body' => $orderName,
            'mch_id' => $wxpayApi['mch_id'],
            'nonce_str' => $this->createNonceStr(),
            'notify_url' => $notifyUrl,
            'out_trade_no' => $outTradeNo,
            'spbill_create_ip' => $this->getIP(),
            'total_fee' => intval($totalFee * 100),       //单位 转为分
            'trade_type' => 'NATIVE'
        );
        // var_dump($wxpayApi);die;
        $unified['sign'] = $this->getSign($unified, $wxpayApi['api_key']);
        //echo $this->arrayToXml($unified);die;
        //var_dump($unified);die;
        $responseXml = self::curlPost('https://api.mch.weixin.qq.com/pay/unifiedorder', $this->arrayToXml($unified));
        $unifiedOrder = simplexml_load_string($responseXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        //var_dump($unifiedOrder);
        $message = '';
        if ($unifiedOrder === false) {
            $message = __('parse xml error');
        }
        if ($unifiedOrder->return_code != 'SUCCESS') {
            $message = $unifiedOrder->return_msg;
        }
        if(!$message){
            $codeUrl = (array)($unifiedOrder->code_url);
            if(!$codeUrl || !$codeUrl[0]){
                $message = __('get code_url error');
            }
        }
        $result = array();
        if($message){
            $result['flag'] = false;
            $result['error_code'] = $unifiedOrder->return_code;
            $result['error_msg'] = $message;
            $message = "order_no: Error Msg:{$message},Error Code:{$unifiedOrder->return_code}";
            $subject = "Wxpay Error Report: MakeQROrder Failed";
            $this->sendErrorEmail($subject, $message);
        } else {
            $result = array(
                "appId" => $wxpayApi['app_id'],
                "timeStamp" => $timestamp,
                "nonceStr" => $this->createNonceStr(),
                "package" => "prepay_id=" . $unifiedOrder->prepay_id,
                "signType" => 'MD5',
                "code_url" => $codeUrl[0],
            );
            $result['paySign'] = $this->getSign($result, $wxpayApi['api_key']);
            $result['flag'] = true;
            $result['order_no'] = $outTradeNo;
        }
        return $result;
    }
    
    /**
     * 统一下单
     * @param string $openid 调用【网页授权获取用户信息】接口获取到用户在该公众号下的Openid
     * @param float $totalFee 收款总费用 单位元
     * @param string $outTradeNo 唯一的订单号
     * @param string $orderName 订单名称
     * @param string $notifyUrl 支付结果通知url 不要有问号
     * @param string $timestamp 支付时间
     * @return string
     */
    function makeJSAPIOrder($sendData) {
        //$openid, $totalFee, $outTradeNo, $orderName, $notifyUrl, $timestamp
        $wxpayApi = Mage::helper('wxpay')->getAppInfo();
        $this->mchid = $wxpayApi['mch_id'];
        $this->appid = $wxpayApi['app_id']; //微信支付申请对应的公众号的APPID
        $this->appKey = $wxpayApi['app_key']; //微信支付申请对应的公众号的APP Key
        $this->apiKey = $wxpayApi['api_key'];
        $totalFee = $sendData['amount'];
        $outTradeNo = $sendData['out_order_no'];
        $orderName = $sendData['order_name'];
        $notifyUrl = $sendData['notify_url'];
        $timestamp = $wxpayApi['timestamp'];
        //$orderName = iconv('GBK','UTF-8',$orderName);
        $unified = array(
            'appid' => $wxpayApi['app_id'],
            'attach' => 'wxpay',             //商家数据包，原样返回，如果填写中文，请注意转换为utf-8
            'body' => $orderName,
            'mch_id' => $wxpayApi['mch_id'],
            'nonce_str' => $this->createNonceStr(),
            'notify_url' => $notifyUrl,
            'openid' => $this->GetOpenid(),//rade_type=JSAPI，此参数必传
            'out_trade_no' => $outTradeNo,
            'spbill_create_ip' => $this->getIP(),
            'total_fee' => intval($totalFee * 100),       //单位 转为分
            'trade_type' => 'JSAPI'
        );
        $unified['sign'] = $this->getSign($unified, $wxpayApi['api_key']);
        $responseXml = $this->curlPost('https://api.mch.weixin.qq.com/pay/unifiedorder', $this->arrayToXml($unified));
        $unifiedOrder = simplexml_load_string($responseXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $message = '';
        if ($unifiedOrder === false) {
            $message = __('parse xml error');
        }
        if ($unifiedOrder->return_code != 'SUCCESS') {
            $message = $unifiedOrder->return_msg;
        }
        $result = array();
        if($message){
            $result['flag'] = false;
            $result['error_code'] = $unifiedOrder->return_code;
            $result['error_msg'] = $message;
            $message = "order_no: Error Msg:{$message},Error Code:{$unifiedOrder->return_code}";
            $subject = "Wxpay Error Report: MakeQROrder Failed";
            $this->sendErrorEmail($subject, $message);
        } else {
            $result = array(
                "appId" => $wxpayApi['app_id'],
                "timeStamp" => "$timestamp",
                "nonceStr" => $this->createNonceStr(),
                "package" => "prepay_id=" . $unifiedOrder->prepay_id,
                "signType" => 'MD5'
            );
            $result['paySign'] = $this->getSign($result, $wxpayApi['api_key']);
            $result['flag'] = true;
            $result['order_no'] = $outTradeNo;
        }
        return $result;
    }
    
    /**
     * 退款
     * @param float $totalFee 订单金额 单位元
     * @param float $refundFee 退款金额 单位元
     * @param string $refundNo 退款单号
     * @param string $wxOrderNo 微信订单号
     * @param string $orderNo 商户订单号
     * @return string
     */
    public function refund($refundData=array())
    {
        $orderNo = $refundData['order_no'];                      //商户订单号（商户订单号与微信订单号二选一，至少填一个）
        //$wxOrderNo = '';                     //微信订单号（商户订单号与微信订单号二选一，至少填一个）
        $totalFee = $refundData['order_amount'];                   //订单金额，单位:元
        $refundFee = $refundData['amount'];                  //退款金额，单位:元
        $refundNo = 'refund_'.$orderNo;        //退款订单号(可随机生成)
        $wxpayApi = Mage::helper('wxpay')->getAppInfo();
        $unified = array(
            'appid' => $wxpayApi['app_id'],
            'mch_id' => $wxpayApi['mch_id'],
            'nonce_str' => $this->createNonceStr(),
            'out_trade_no'=>$orderNo,        //商户订单号
            'out_refund_no'=>$refundNo,        //商户退款单号
            'refund_fee' => intval($refundFee * 100),       //退款金额 单位 转为分
            'total_fee' => intval($totalFee * 100),       //订单金额     单位 转为分
            'sign_type' => 'MD5',           //签名类型 支持HMAC-SHA256和MD5，默认为MD5
            //'transaction_id'=>$wxOrderNo,               //微信订单号
            'refund_desc'=>'正常退款',     //退款原因（选填）
        );
        //var_dump($unified);die;
        $unified['sign'] = $this->getSign($unified, $wxpayApi['api_key']);
        //echo dirname(__FILE__);die;
        //print_r($this->arrayToXml($unified));die;
        $responseXml = $this->curlPost('https://api.mch.weixin.qq.com/secapi/pay/refund', $this->arrayToXml($unified),true);
        //var_dump($responseXml);
        /* 
        <xml><return_code><!--[CDATA[SUCCESS]]--></return_code>
        <return_msg><!--[CDATA[OK]]--></return_msg>
        <appid><!--[CDATA[wxde71f3f3f2f504ac]]--></appid>
        <mch_id><!--[CDATA[1498366722]]--></mch_id>
        <nonce_str><!--[CDATA[huWT5PMykGTCIXal]]--></nonce_str>
        <sign><!--[CDATA[09FF46DAB0CFF9A9738234917CF74852]]--></sign>
        <result_code><!--[CDATA[FAIL]]--></result_code>
        <err_code><!--[CDATA[NOTENOUGH]]--></err_code>
        <err_code_des><!--[CDATA[基本账户余额不足，请充值后重新发起]]--></err_code_des>
        </xml>
        */
        $unifiedOrder = simplexml_load_string($responseXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        
        $message = '';
        $return_code = '';
        $return_msg = '';
        if ($unifiedOrder === false) {
            $message = __('parse xml error');
        }
        if($unifiedOrder){
            $unifiedOrder = json_encode($unifiedOrder);
            $unifiedOrder = json_decode($unifiedOrder,true);
            if($unifiedOrder['return_code'] == 'SUCCESS'){
                if($unifiedOrder['result_code'] == 'SUCCESS'){
                    $return_code = $unifiedOrder['result_code'];
                    $return_msg = $unifiedOrder['result_code'];
                }else{
                    $return_code = $unifiedOrder['err_code'];
                    $return_msg = $unifiedOrder['err_code_des'];
                    $message = $return_msg;
                }
            }else{
                $return_code = $unifiedOrder['return_code'];
                $return_msg = $unifiedOrder['return_msg'];
                $message = $return_code .'|'.$return_msg;
            }
        }
        $result = array(
                    'flag'=>true,
                    'return_code'=> $return_code,
                    'return_msg'=> $return_msg,
                    'data'=> $unifiedOrder
                );
        if($message){
            $result['flag'] = false;
            $result['return_msg'] = $message;
        }
        return $result;
    }

    
    /**
     * 退款查询
     * @param string $refundNo 商户退款单号
     * @param string $wxOrderNo 微信订单号
     * @param string $orderNo 商户订单号
     * @param string $refundId 微信退款单号
     * @return string
     */
    public function doRefundQuery($refundData=array())
    {
        //以下四个单号四选一。查询的优先级是： 微信退款单号 > 商户退款订单号 > 微信订单号 > 商户订单号
        $orderNo = '';                      //商户订单号
        $wxOrderNo = '';                     //微信订单号
        $refundNo='';                       //商户退款订单号
        $refundId = '';                     //微信退款单号（微信生成的退款单号，在申请退款接口有返回）
        $wxpayApi = Mage::helper('wxpay')->getAppInfo();
        $unified = array(
            'appid' => $wxpayApi['app_id'],
            'mch_id' => $wxpayApi['mch_id'],
            'nonce_str' => $this->createNonceStr(),
            'sign_type' => 'MD5',           //签名类型 支持HMAC-SHA256和MD5，默认为MD5
            'transaction_id'=>$wxOrderNo,               //微信订单号
            'out_trade_no'=>$orderNo,        //商户订单号
            'out_refund_no'=>$refundNo,        //商户退款单号
            'refund_id'=>$refundId,     //微信退款单号
        );
        $unified['sign'] = $this->getSign($unified, $wxpayApi['api_key']);
        $responseXml = $this->curlPost('https://api.mch.weixin.qq.com/pay/refundquery', $this->arrayToXml($unified));
        //file_put_contents('2.txt',$responseXml);
        $unifiedOrder = simplexml_load_string($responseXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $message = '';
        if ($unifiedOrder === false) {
            $message = __('parse xml error');
        }
        if ($unifiedOrder->return_code != 'SUCCESS') {
            $message = $unifiedOrder->return_msg;
        }
        $unifiedOrder = json_decode($unifiedOrder,true);
        $result = array('flag'=>true,'data'=>$unifiedOrder);
        if($message){
            $result['flag'] = false;
            $result['message'] = $message;
        }
        return $result;
    }

    public function sendErrorEmail($subject, $message) {
        return ;
        $from = Mage::getStoreConfig('trans_email/ident_sales/name') . ' <' . Mage::getStoreConfig('trans_email/ident_sales/email') . '>';
        $headers = "From: $from";
        $toArray = $this->getConfigData('error_report_receivers');
        if ($toArray) {
            $toArray = explode(',', $toArray);
            foreach ((array) $toArray as $to) {
                mail($to, $subject, $message, $headers);
            }
        }
    }

    /*
     * 查询订单状态
     * 商户生成订单之后
     */
    function queryOrder($sendData) {
        //公众账号ID    appid
        //商户号   mch_id
        //微信订单号 transaction_id
        //商户订单号 out_trade_no
        //随机字符串 nonce_str
        //签名    sign
        //签名类型  sign_type
        $wxpayApi = Mage::helper('wxpay')->getAppInfo();
        $outTradeNo = $sendData['order_no'];
        //$orderName = iconv('GBK','UTF-8',$orderName);
        $unified = array(
            'appid' => $wxpayApi['app_id'],
            'mch_id' => $wxpayApi['mch_id'],
            'nonce_str' => $this->createNonceStr(),
            'out_trade_no' => $outTradeNo,
        );
        // var_dump($wxpayApi);die;
        $unified['sign'] = $this->getSign($unified, $wxpayApi['api_key']);
        //echo $this->arrayToXml($unified);die;
        //var_dump($unified);die;
        $responseXml = self::curlPost('https://api.mch.weixin.qq.com/pay/orderquery', $this->arrayToXml($unified));
        $unifiedOrder = simplexml_load_string($responseXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        //var_dump($unifiedOrder);die;
        $message = '';
        if ($unifiedOrder === false) {
            $message = __('parse xml error');
        }
        if ($unifiedOrder->return_code != 'SUCCESS') {
            $message = $unifiedOrder->return_msg;
        }

        $result = array();
        if($message){
            $result['flag'] = false;
            $result['result_code'] = $unifiedOrder->return_code;
            $result['error_code'] = $unifiedOrder->return_code;
            $result['error_msg'] = $unifiedOrder->return_msg;
            $subject = "wxpay Error Report: QueryOrder Failed";
            $this->sendErrorEmail($subject, $message);
        } else {
            $result['result_code'] = $unifiedOrder->trade_state;
            $result['return_msg'] = $unifiedOrder->trade_state_desc;
            $result['error_code'] = $unifiedOrder->err_code;
            $result['error_msg'] = $unifiedOrder->err_code_des;
            $result['flag'] = true;
            $result['transaction_id'] = $unifiedOrder->transaction_id;
            $result['order_no'] = $unifiedOrder->out_trade_no;
        }
        return $result;
    }

    /**
     * 通过跳转获取用户的openid，跳转流程如下：
     * 1、设置自己需要调回的url及其其他参数，跳转到微信服务器https://open.weixin.qq.com/connect/oauth2/authorize
     * 2、微信服务处理完成之后会跳转回用户redirect_uri地址，此时会带上一些参数，如：code
     * @return 用户的openid
     */
    public function GetOpenid()
    {
        //通过code获得openid
        if (!isset($_GET['code'])){
            //触发微信返回code码
            $scheme = $_SERVER['HTTPS']=='on' ? 'https://' : 'http://';
            $baseUrl = urlencode($scheme.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].$_SERVER['QUERY_STRING']);
            $url = $this->__CreateOauthUrlForCode($baseUrl);
            Header("Location: $url");
            exit();
        } else {
            //获取code码，以获取openid
            $code = $_GET['code'];
            $openid = $this->getOpenidFromMp($code);
            return $openid;
        }
    }
    /**
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 微信跳转回来带上的code
     * @return openid
     */
    public function GetOpenidFromMp($code)
    {
        $url = $this->__CreateOauthUrlForOpenid($code);
        $res = $this->curlGet($url);
        //取出openid
        $data = json_decode($res,true);
        $this->data = $data;
        $openid = $data['openid'];
        return $openid;
    }
    /**
     * 构造获取open和access_toke的url地址
     * @param string $code，微信跳转带回的code
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code)
    {
        $urlObj["appid"] = $this->appid;
        $urlObj["secret"] = $this->appKey;
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }
    /**
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl)
    {
        $urlObj["appid"] = $this->appid;
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
        $urlObj["scope"] = "snsapi_base";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }
    /**
     * 拼接签名字符串
     * @param array $urlObj
     * @return 返回已经拼接好的字符串
     */
    private function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            if($k != "sign") $buff .= $k . "=" . $v . "&";
        }
        $buff = trim($buff, "&");
        return $buff;
    }
}
