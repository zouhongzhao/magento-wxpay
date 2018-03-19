<?php

class Zou_Wxpay_Helper_Data extends Mage_Core_Helper_Abstract
{
    const API_BASE_URL = "https://openapi.alipay.com/gateway.do?";
    const API_PARAMS = "";
    const SIGN_TYPE = 'RSA2';//signType
    const CHARSET = 'utf8';//charset
    const FORMAT = 'JSON';//format
    const VERSION = '1.0';//version
    protected $rsaPrivateKey;
    public function isEnabled() {
        return $this->getConfigData('active');
    }
    function getIP(){
        //strcasecmp 比较两个字符，不区分大小写。返回0，>0，<0。
        if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $res =  preg_match ( '/[\d\.]{7,15}/', $ip, $matches ) ? $matches [0] : '';
        return $res;
        //dump(phpinfo());//所有PHP配置信息
    }
    public function getAppInfo(){
        $apiData = array(
            'mch_id'=>$this->getConfigData('mch_id'),
            'app_id'=> $this->getConfigData('app_id'),
            'app_key'=> $this->getConfigData('app_key'),
            'api_key'=>$this->getConfigData('api_key'),
            'timestamp'=>$this->getMillisecond(),
            'nonce_str'=>$this->getRandChar(20),
            'api_url'=>self::API_BASE_URL.self::API_PARAMS,
            'sign_type'=>self::SIGN_TYPE,
            'charset'=>self::CHARSET,
            'format'=>self::FORMAT,
            'version'=>self::VERSION
        );
        return $apiData;
    }
    
    public function getMillisecond() {
        list($t1, $t2) = explode(' ', microtime());
        return (string)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }
    
    public function getRandChar($length){
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol)-1;
        for($i=0;$i<$length;$i++){
            $str.=$strPol[rand(0,$max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }
        return $str;
    }
    /**
     * Get default settings
     *
     * @param string $code            
     * @return mixed
     */
    public function getConfigData($code, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = Mage::app()->getStore()->getStoreId();
        }
        
        return trim(Mage::getStoreConfig("payment/wxpay/$code", $storeId));
    }
    public function is_omipay_app(){
        return strripos($_SERVER['HTTP_USER_AGENT'],'micromessenger')!=false;
    }
    /**
     * Get order
     *
     * @param string $orderId            
     * @return Mage_Sales_Model_Order
     */
    public function getOrder($orderId = null)
    {
        if (null === $orderId) {
            $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        }
        
        return Mage::getModel('sales/order')->loadByIncrementId($orderId);
    }

    public function get_order_title($order, $limit = 98) {
        $subject = "#{$order->getRealOrderId()}";
//         $items =$order->getAllItems();
//         if($items&&count($items)>0){
//             $index=0;
//             foreach ($items as $item){
//                 $subject.= "|{$item->getName()}";
//                 if($index++>0){
//                     $subject.='...';
//                     break;
//                 }
//             }
//         }
        return mb_strimwidth($subject, 0, $limit);
    }
    
    public function get_order_desc($order) {
        $descs=array();
        $items =$order->getAllItems();
        if( $items){
            foreach ( $items as $item){
    
                $desc=array(
                    'id'=>$item->getProductId(),
                    'order_qty'=>$item->getQtyOrdered(),
                    'order_item_id'=>$item->getQtyOrdered(),
                    'url'=>'',
                    'sale_price'=>round($item->getPrice(),2),
                    'image'=>'',
                    'title'=>'',
                    'sku'=>$item->getProductId(),
                    'summary'=>'',
                    'content'=>''
                );
                 
                $descs[]=$desc;
            }
        }
         
        return json_encode($descs);
    }

    public function createNonceStr($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
    public function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
        }
        $xml .= "</xml>";
        return $xml;
    }
    /**
     * 获取签名
     */
    public function getSign($params, $key)
    {
        ksort($params, SORT_STRING);
        $unSignParaString = self::formatQueryParaMap($params, false);
        //echo $unSignParaString;
        $signStr = strtoupper(md5($unSignParaString . "&key=" . $key));
        //var_dump($signStr);die;
        return $signStr;
    }
    protected function formatQueryParaMap($paraMap, $urlEncode = false)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if (null != $v && "null" != $v) {
                if ($urlEncode) {
                    $v = urlencode($v);
                }
                $buff .= $k . "=" . $v . "&";
            }
        }
        $reqPar = '';
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }
    
    public function isWebApp()
    {
        if (! isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        
        $u = strtolower($_SERVER['HTTP_USER_AGENT']);
        if ($u == null || strlen($u) == 0) {
            return false;
        }
        
        preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/', $u, $res);
        
        if ($res && count($res) > 0) {
            return true;
        }
        
        if (strlen($u) < 4) {
            return false;
        }
        
        preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/', substr($u, 0, 4), $res);
        if ($res && count($res) > 0) {
            return true;
        }
        
        $ipadchar = "/(ipad|ipad2)/i";
        preg_match($ipadchar, $u, $res);
        return $res && count($res) > 0;
    }

    public function log($message, $level = null)
    {
        try {
            $logActive = Mage::getStoreConfig('dev/log/active');
        } catch (Exception $e) {
            $logActive = true;
        }
        if (! Mage::getIsDeveloperMode() && ! $logActive) {
            return;
        }
        
        static $loggers = array();
        
        $level = is_null($level) ? Zend_Log::DEBUG : $level;
        $file = 'log-' . date('d') . '.log';
        try {
            if (! isset($loggers[$file])) {
                $logDir = Mage::getBaseDir() .DS.'var'. DS . 'log' . DS . date("Y-m");
                $logFile = $logDir . DS . $file;
                //echo $logDir;
                if (!@is_dir($logDir) && ! @mkdir($logDir, 0777,true)) {
                    return;
                }
                
                if (! file_exists($logFile)) {
                    @file_put_contents($logFile, '');
                    @chmod($logFile, 0777);
                }
                $format = '%timestamp% %priorityName% (%priority%): %message%' . PHP_EOL;
                $formatter = new Zend_Log_Formatter_Simple($format);
                $writerModel = (string) Mage::getConfig()->getNode('global/log/core/writer_model');
                if (! Mage::app() || ! $writerModel) {
                    $writer = new Zend_Log_Writer_Stream($logFile);
                } else {
                    $writer = new $writerModel($logFile);
                }
                $writer->setFormatter($formatter);
                $loggers[$file] = new Zend_Log($writer);
            }
            
            if (is_array($message) || is_object($message)) {
                $message = print_r($message, true);
            }
            
            $stack = "[";
            $debugInfo = debug_backtrace();
            foreach ($debugInfo as $key => $val) {
                if (array_key_exists("file", $val)) {
                    $stack .= ",file:" . $val["file"];
                }
                if (array_key_exists("line", $val)) {
                    $stack .= ",line:" . $val["line"];
                }
                if (array_key_exists("function", $val)) {
                    $stack .= ",function:" . $val["function"];
                }
            }
            $stack .= "]";
            $loggers[$file]->log($stack . $message, $level);
        } catch (Exception $e) {}
    }
    
    public function getBarcode($barcodeString){
        $file = Zend_Barcode::draw('code128', 'image', array('text' => $barcodeString), array());
        $fileName = $file.'.png';
        $barcodePath = Mage::getBaseDir('media').'omipay/barcode/';
        if(!is_dir($barcodePath)){
            @mkdir($barcodePath,0777,true);
        }
        $store_image = imagepng($file,$barcodePath);
        $img = str_replace(Mage::getBaseDir('media'), Mage::getBaseUrl('media'), $barcodePath.$fileName);
        return $img;
    }
    
    public function getQrCode($order_no,$url){
        $moduleLibDir = Mage::getModuleDir('lib', 'Omipay_Payment');
        include $moduleLibDir.'/lib/phpqrcode/phpqrcode.php';
        $dir = Mage::getBaseDir('media').'/omipay/qrcode/';
        if(!is_dir($dir)){
            @mkdir($dir,0777,true);
        }
        $fileName = $order_no . '.png';
        $filePath = $dir . $fileName;
        //echo $url;
        //if(!file_exists($filePath)){
            QRcode::png($url,$filePath,'M',9);
            @chmod($dir, 0777);
        //}
        $img = str_replace(Mage::getBaseDir('media'), Mage::getBaseUrl('media'), $filePath);
        return $img;
    }

    /**
     *  验证签名
     **/
    public function rsaCheck($params) {
        $sign = $params['sign'];
        $signType = $params['sign_type'];
        unset($params['sign_type']);
        unset($params['sign']);
        return $this->verify($this->getSignContent($params), $sign, $signType);
    }

    function verify($data, $sign, $signType = 'RSA') {
        $pubKey= $this->getConfigData('app_public_key');
        $res = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($pubKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";
        ($res) or die('支付宝RSA公钥错误。请检查公钥文件格式是否正确');
        //调用openssl内置方法验签，返回bool值
        if ("RSA2" == $signType) {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res, version_compare(PHP_VERSION,'5.4.0', '<') ? SHA256 : OPENSSL_ALGO_SHA256);
        } else {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res);
        }
//        if(!$this->checkEmpty($this->alipayPublicKey)) {
//            //释放资源
//            openssl_free_key($res);
//        }
        return $result;
    }

}
