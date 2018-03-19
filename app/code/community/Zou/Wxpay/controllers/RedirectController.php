<?php
class Zou_Wxpay_RedirectController extends Mage_Core_Controller_Front_Action {

//     protected function _expireAjax() {
//         if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
//             $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
//             exit;
//         }
//     }
    
//     protected function _getCheckout()
//     {
//         return Mage::getSingleton('checkout/session');
//     }

    public function indexAction() {
        $orderId = $this->getRequest()->get('orderId');
        if ($orderId) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        } else {
            $order = Mage::helper('wxpay')->getOrder();
        }
        $order_id = $order->getRealOrderId();
//         var_dump($order_id);
//         var_dump($order->getState());die;
        if(!in_array($order->getState(), array(
            Mage_Sales_Model_Order::STATE_NEW,
            Mage_Sales_Model_Order::STATE_PENDING_PAYMENT
        ))){
            $this->_redirectUrl(Mage::getUrl('wxpay/redirect/success', array('orderId' => $order_id)));
            return;
        }
        if(!($order && $order instanceof Mage_Sales_Model_Order)){
           throw new Exception('unknow order');
        }
        
        $payment = $order->getPayment();
        $orderIncrementId = $order->getIncrementId();
        if( $payment->getMethod() !='wxpay'){
            throw new Exception('unknow order payment method');
        }
        
        $protocol = (! empty ( $_SERVER ['HTTPS'] ) && $_SERVER ['HTTPS'] !== 'off' || $_SERVER ['SERVER_PORT'] == 443) ? "https://" : "http://";
        $website = $protocol.$_SERVER['HTTP_HOST'];
        
        $total_amount     = round($order->getGrandTotal(),2);
        
        $helper = Mage::helper('wxpay');
        //$helper->log('test');die;
        $apiHelper = Mage::helper('wxpay/api');
        $appId = $helper->getConfigData('app_id');
        try {
            if($helper->isWebApp()){
                $data = array(
                    "order_name" => $helper->get_order_title($order),
                    "currency" => $order->getOrderCurrencyCode(),
                    "amount" => $total_amount,
                    "redirect_url"=>Mage::getUrl('wxpay/redirect/success', array('orderId' => $order_id)),
                    "notify_url" => Mage::getUrl('wxpay/notify'),
                    "out_order_no" => $orderIncrementId
                );
                $result = $apiHelper->makeJSAPIOrder($data);
                //print_r($result);die;
                if(!$result['flag']){
                    $helper->log('order:'.$orderIncrementId);
                    $helper->log($result);
                    throw new Exception($helper->__($result['error_code']),500);
                }
                $jsApiParameters = json_encode($result);
                //$order->setWxpayOrderNo($result['order_no'])->save();
                $session = Mage::getSingleton('checkout/session');
                $session->setQuoteId($order->getRealOrderId());
                $session->getQuote()
                ->setIsActive(false)
                ->save();
                $payAmount = Mage::helper('core')->formatPrice($total_amount, false);
                ?>
                <!DOCTYPE html>
                    <html>
                        <head>
                            <meta charset="utf-8" />
                            <meta name="viewport" content="width=device-width, initial-scale=1"/>
                            <title>微信支付</title>
                            <script type="text/javascript">
                                //调用微信JS api 支付
                                function jsApiCall()
                                {
                                    WeixinJSBridge.invoke(
                                        'getBrandWCPayRequest',
                                        <?php echo $jsApiParameters; ?>,
                                        function(res){
                                            WeixinJSBridge.log(res.err_msg);
                                            alert(res.err_code+res.err_desc+res.err_msg);
                                        }
                                    );
                                }
                                function callpay()
                                {
                                    if (typeof WeixinJSBridge == "undefined"){
                                        if( document.addEventListener ){
                                            document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
                                        }else if (document.attachEvent){
                                            document.attachEvent('WeixinJSBridgeReady', jsApiCall);
                                            document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
                                        }
                                    }else{
                                        jsApiCall();
                                    }
                                }
                            </script>
                        </head>
                        <body>
                        <br/>
                        <font color="#9ACD32"><b>该笔订单支付金额为<span style="color:#f00;font-size:50px"><?php echo $payAmount?>元</span>钱</b></font><br/><br/>
                        <div align="center">
                            <button style="width:210px; height:50px; border-radius: 15px;background-color:#FE6714; border:0px #FE6714 solid; cursor: pointer;  color:white;  font-size:16px;" type="button" onclick="callpay()" >立即支付</button>
                        </div>
                        </body>
                        </html>
                <?php
            }else{
               //生成二维码
               $data = array(
                   "order_name" => $helper->get_order_title($order),
                   "currency" => $order->getOrderCurrencyCode(),
                   "amount" => $total_amount,
                   "notify_url" => Mage::getUrl('wxpay/notify'),
                   "out_order_no" => $orderIncrementId,
               );
               //print_r($data);
               //$arr = $wxPay->createJsBizPackage($payAmount,$outTradeNo,$orderName,$notifyUrl,$payTime);
               $result = $apiHelper->makeQROrder($data);
               if(!$result['flag']){
                   $helper->log('order:'.$orderIncrementId);
                   $helper->log($result);
                   throw new Exception($helper->__($result['error_code']),500);
               }
               //$order->setWxpayOrderNo($result['order_no'])->save();
               $queryUrl = Mage::getUrl('wxpay/redirect/query/');//,array('order_no'=>$result['order_no']));
               //var_dump($orderId);
               //echo $queryUrl;
               //$qrCode = $result['qrcode'];
               //echo $qrUrl;
               //$qrImgUrl = 'http://mobile.qq.com/qrcode?url='.$qrCode;
               //$qrImgUrl = $helper->getQrCode($orderIncrementId,$qrCode);
               //生成二维码
               $qrImgUrl = 'http://pan.baidu.com/share/qrcode?w=300&h=300&url='.$result['code_url'];
               $itemsHtml = '';
               $items =$order->getAllItems();
               if($items && count($items) > 0){
                   foreach ($items as $item){
                       $itemsHtml .= '<p class="item-detail">'.$item->getName().'</p>';
                   }
               }
               $wxTypeClass = 'wechat';
               $total_amount = Mage::helper('core')->formatPrice($total_amount, false);
               $storeName = Mage::app()->getStore()->getName();
                ?>
                    <html>
                        <head>
                            <title>Wxpay</title>
                            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                            <meta http-equiv="X-UA-Compatible" content="IE=Edge">
                            <link rel="stylesheet" href="//cdn.bootcss.com/bootstrap/3.3.4/css/bootstrap.min.css">
                            <link rel="stylesheet" href="<?php echo Mage::getDesign()->getSkinUrl('zou/css/QRcode_Pay.css')?>">
                            <script src="//cdn.bootcss.com/jquery/3.1.1/jquery.min.js"></script>
                        </head>
                        <body>
                            <section class="main">
                                <div class="card <?php echo $wxTypeClass?>">
                                    <div id="weixin-warning" class="alert alert-warning" role="alert">
                                        如果二维码超时,请点<a href="#" class='refresh-page'>我</a>刷新。
                                    </div>
                                    <div id="weixin-notice" class="alert alert-success" role="alert" style="display:none"></div>
                                    <div class="card_body clear">
                                        <div class="card-left lf">
                                            <div class="logo-box">
                                                <i class="logo"></i>
                                            </div>
                                            <ul class="list detail-list">
                                                <li class="list-item amount clear">
                                                    <p class="currency">CNY</p>
                                                    <p><span><?php echo $total_amount?></span></p>
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="card-right rt">
                                            <img src="<?php echo $qrImgUrl?>" class="qr-code">
                                        </div>
                                    </div>
                                    <div class="card_footer card_bg">
                                        <ul class="list">
                                            <li class="list-item">
                                                <p class="item-name">Content</p>
                                                <?php echo $itemsHtml?>
                                            </li>
                                            <li class="list-item">
                                                <p class="item-name">Company</p>
                                                <p class="item-detail"><?php echo $storeName?></p>
                                            </li>
                                            <li class="list-item">
                                                <p class="item-name">Order ID</p>
                                                <p class="item-detail"><?php echo $result['order_no']?></p>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </section>
                            <script type="text/javascript">
                            (function($){
                                $('#weixin-warning .refresh-page').on('click',function(){
                                        window.location.reload();
                                        return false;
                                })
                                window.view={
                                    query:function () {
                                        $.ajax({
                                            type: "POST",
                                            url: '<?php echo $queryUrl?>',
                                            timeout:60000,
                                            cache:false,
                                            dataType:'json',
                                            data:{'order_id':'<?php echo $orderIncrementId?>','order_no':'<?php echo $result['order_no']?>'},
                                            success:function(data){
                                                if (data && data.status == 'paid') {
                                                    $('#weixin-notice').text('已支付成功，跳转中...').show();
                                                    location.href = data.message;
                                                    return;
                                                }else if(data.status == 'SIGN_TIMEOUOT'){
                                                    window.location.reload();
                                                    return;
                                                }
                                                setTimeout(function(){window.view.query();}, 3000);
                                            },
                                            error:function(){
                                                setTimeout(function(){window.view.query();}, 3000);
                                            }
                                        });
                                    }
                                };
                                setInterval(window.view.query(),3000);
                            })(jQuery);
                            </script>
                        </body>
                   </html>
                  <?php
            }
            
        } catch (Exception $e) {
            ?>
            <html>
            <meta charset="utf-8" />
            <title><?php print $helper->__('System error!')?></title>
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta content="width=device-width, initial-scale=1.0" name="viewport" />
            
            <head>
                <title><?php print $helper->__('Ops!Something is wrong.')?></title>
            </head>
            <body>
            <?php 
               echo "errcode:{$e->getCode()},errmsg:{$e->getMessage()}";
           ?>
           </body>
           </html>
           <?php
        }
        
        exit;
    }
    
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }
    public function successAction() {
        //$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        $orderId = $this->getRequest()->get('orderId');
        if ($orderId) {
            $transactionId = $this->getRequest()->get('transaction_id');
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            if($order){
                $order->setWxpayOrderNo($transactionId);
                Mage::getModel('wxpay/pay')->processResponse($order);
            }
        }
        $this->_redirect('checkout/onepage/success', array('_secure'=>true));
    }
    public function queryAction() {
        $orderNo = $this->getRequest()->get('order_no');
        $orderId = $this->getRequest()->get('order_id');
        $result = array('status'=>false);
        if ($orderNo) {
            $apiHelper = Mage::helper('wxpay/api');
            $data = array(
                'order_no'=>$orderNo
            );
            $res = $apiHelper->queryOrder($data);
            //var_dump($res);
            if($res['flag'] && $res['result_code'] == 'SUCCESS'){
                $result['status'] = 'paid';
                $result['message'] = Mage::getUrl('wxpay/redirect/success', array('orderId' => $orderId,'transaction_id'=>$res['transaction_id']));
            }
            if(!$res['flag'] && $res['error_code']){
                $result['status'] = $res['error_code'];
            }
        }
        echo json_encode($result);die;
    }
}
?>