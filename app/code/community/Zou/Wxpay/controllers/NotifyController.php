<?php
class Zou_Wxpay_NotifyController extends Mage_Core_Controller_Front_Action
{
    /**
     * Instantiate notify model and pass notify request to it
     */
    public function indexAction()
    {
//         if (!$this->getRequest()->isPost()) {
//             return;
//         }
        $data = $this->getRequest()->getPost();
        
        $helper = Mage::helper('wxpay');
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        $helper->log( $postObj);
        if ($postObj === false) {
            die('parse xml error');
        }
        if ($postObj->return_code != 'SUCCESS') {
            die($postObj->return_msg);
        }
        $data = (array)$postObj;
        unset($data['sign']);
        if ($helper->getSign($data, $config['key']) != $postObj->sign) {
            return;
        }
        //$helper->log( $data);
        //微信支付订单号   transaction_id
        //商户订单号 out_trade_no
        $order_id = $data['out_order_no'];
        $transaction_id = isset($data['transaction_id'])?$data['transaction_id']:'';
        
        try{
            $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
            if (! $order || ! $order->getId() || ! $order instanceof Mage_Sales_Model_Order) {
                throw new Exception('unknow order');
            }
            
            if (!in_array($order->getState(), array(
                Mage_Sales_Model_Order::STATE_NEW,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT
            
            ))) {
                $params = array(
                    'action'=>'success',
                    'm_number'=>$app_id,
                );
                $params['hash'] = $hash;
                ob_clean();
                print json_encode($params);
                exit;
            }
             
            $payment = $order->getPayment();
            if( $payment->getMethod() != 'wxpay'){
                throw new Exception('unknow order payment method');
            }
            
            $payment->setTransactionId($transaction_id)
            ->registerCaptureNotification($order->getGrandTotal(), true);
            $order->save();
            
            // notify customer
            $invoice = $payment->getCreatedInvoice();
            if ($invoice && ! $order->getEmailSent()) {
                $order->sendNewOrderEmail()
                ->addStatusHistoryComment(Mage::helper('omipay')->__('Notified customer about invoice #%s.', $invoice->getIncrementId()))
                ->setIsCustomerNotified(true)
                ->save();
            }
            $session = Mage::getSingleton('checkout/session');
            $session->setQuoteId($order->getQuoteId());
            $session->getQuote()->setIsActive(false)->save();
            
        }catch(Exception $e){
            //looger
            $helper->log( $e->getMessage());
            $params = array(
                'action'=>'fail',
                'appid'=>$app_id,
                'errcode'=>$e->getCode(),
                'errmsg'=>$e->getMessage()
            );
        
            $params['hash'] = $hash;//$helper->generate_xh_hash($params, $hashkey);
            ob_clean();
            print json_encode($params);
            exit;
        }
        
        $params = array(
            'action'=>'success',
            'appid'=>$app_id
        );
        
        $params['hash']= $hash;//$helper->generate_xh_hash($params, $hashkey);
        ob_clean();
        print json_encode($params);
        exit;
    }
}
