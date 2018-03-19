<?php

class Zou_Wxpay_Model_Pay extends Mage_Payment_Model_Method_Abstract {
    protected $_code          = 'wxpay';
    protected $_formBlockType = 'wxpay/form';
     //protected $_infoBlockType = 'wxpay/info';
    protected $_order;
    protected $_isGateway               = false;
    protected $_canAuthorize            = false;
    protected $_canCapture              = true;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;
    protected $_canRefund               = true;

    /**
     *
     * @return string
     */
    public function getNewOrderState()
    {
        return Mage_Sales_Model_Order::STATE_NEW;
    }
    
    /**
     *
     * @return string
     */
    public function getNewOrderStatus()
    {
        return Mage::getStoreConfig("payment/wxpay/order_status");
    }
    
    /**
     * Get order model
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $this->_order = $this->getInfoInstance()->getOrder();
        }
        return $this->_order;
    }
    /**
     * Get config payment action, do nothing if status is pending
     *
     * @return string|null
     */
    public function getConfigPaymentAction()
    {
        return $this->getConfigData('order_status') == 'pending' ? null : parent::getConfigPaymentAction();
    }
    
    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('wxpay/redirect', array('_secure' => true));
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $payment->setStatus(self::STATUS_APPROVED)->setLastTransId($this->getTransactionId());
        return $this;
    }
    public function authorize(Varien_Object $payment, $amount) {
        if (!$this->canAuthorize()){
            $payment->setTransactionId(time());
            $payment->setIsTransactionClosed(0);
        }
        return $this;
    }
    
    public function getRepayUrl($order){
        return Mage::getUrl('wxpay/redirect', array('_secure' => true,'orderId'=>$order->getRealOrderId()));
    }
    
    /**
     * Mock capture transaction id in invoice
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Mage_Payment_Model_Method_Abstract
     */
    public function processInvoice($invoice, $payment)
    {
        $invoice->setTransactionId(1);
        return $this;
    }

    /**
     * Set transaction ID into creditmemo for informational purposes
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Mage_Payment_Model_Method_Abstract
     */
    public function processCreditmemo($creditmemo, $payment)
    {
        $creditmemo->setTransactionId(1);
        return $this;
    }
    public function refund(Varien_Object $payment, $amount){
        $order = $payment->getOrder();
        $result = $this->callApi($payment,$amount,'refund');
        if(!$result['status']) {
            $errorMsg = $result['message']?$result['message']:'Invalid Data';
            //$errorMsg = $this->_getHelper()->__('Error Processing the request');
            Mage::throwException($errorMsg);
        }
        return $this;
    }
    
    private function callApi(Varien_Object $payment, $amount,$type){

        $result = array('status'=>1,'message'=>'');
        $order = $payment->getOrder();
        $billingaddress = $order->getBillingAddress();
        $totals = number_format($amount, 2, '.', '');
        $orderId = $order->getIncrementId();
        $currencyDesc = $order->getBaseCurrencyCode();
        
        if($type == 'refund'){
            //$amount = $totals * 100;
            $orderAmount = $order->getGrandTotal();
            $refundData = array('wxpay_order_no'=>$order->getData('wxpay_order_no'),'order_no'=>$orderId,'order_amount'=>$orderAmount,'amount'=>$amount);
            $refundDataRow = Mage::helper('wxpay/api')->refund($refundData);
            // var_dump($refundDataRow);die;
            $insertData = array(
                'order_no'=>$orderId,
                'customer_id'=>$order->getCustomerId(),
                'customer_email'=>$order->getCustomerEmail(),
                'status'=>$refundDataRow['flag']?1:2,
                'result_code'=>$refundDataRow['return_code'],
                'result_msg'=>$refundDataRow['return_msg'],
                'message'=> json_encode($refundDataRow['data'])
            );
            if($refundDataRow['flag']){
                $insertData['refund_time'] = time();
                $insertData['currency'] = isset($refundDataRow['data']['fee_type'])?$refundDataRow['data']['fee_type']:'';
                $insertData['out_refund_no'] = $refundDataRow['data']['out_refund_no'];//商户退款单号
                $insertData['transaction_id'] = $refundDataRow['data']['transaction_id'];//微信订单号
                $insertData['refund_id'] = $refundDataRow['data']['refund_id'];//微信退款单号
                $insertData['amount'] = $refundDataRow['data']['refund_fee']/100;
                $result['transaction_id'] = $insertData['out_refund_no'];
                //print_r($insertData);die;
                $refundModel = Mage::getModel('wxpay/refund');
                $refundModel->setData($insertData);
                $refundModel->save();
            }else{
                $result['status'] = 0;
                $result['message'] = $insertData['result_msg'];
            }
        }
    
        return $result;
    }
    public function getOrderEmailStatus() {
        return Mage::getStoreConfig('payment/wxpay/order_email_status');
    }
    public function processResponse($order){
        if ($order->canInvoice()) {
            $payment = $order->getPayment();
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->register();
            if ($this->getInvoiceEmailStatus() != 1) {
                $invoice->setEmailSent(true);
                $invoice->sendEmail();
            }
            $invoice->save();
            $newOrderStatus = 'processing';
            $notify = ($this->getOrderEmailStatus() == 1) ? true : false;
            $paymentDescription = Mage::helper('wxpay')->__('Received wxpay verification. %s payment method was used.', 'wxpay');
            $order->setStatus(__('Processing'))->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING, $newOrderStatus, $paymentDescription, $notify
                )->save();
                if ($notify) {
                    $order->sendNewOrderEmail()->addStatusHistoryComment(
                        Mage::helper('svm')->__('Order confirmation sent.')
                        )
                        ->setIsCustomerNotified(true)
                        ->save();
                }
        }
        $session = Mage::getSingleton('checkout/session');
        $session->setLastSuccessQuoteId($order->getQuoteId())
        ->setLastQuoteId($order->getQuoteId())
        ->addSuccess(Mage::helper('wxpay')->__('Your Payment was Successful!'))
        ->setLastOrderId($order->getId())
        ->setLastRealOrderId($order->getIncrementId());
        $session->getQuote()->setIsActive(false)->save();
    }
}
