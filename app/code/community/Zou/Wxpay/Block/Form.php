<?php
class Zou_Wxpay_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('wxpay/form.phtml');
        $this->setMethodTitle('');
    }

    public function getMethodLabelAfterHtml()
    {
        if (!$this->hasData('_method_label_html')) {
            $code = $this->getMethod()->getCode();
            $labelBlock = Mage::app()->getLayout()->createBlock('core/template', null, array(
                'template' => 'wxpay/payment_method_label.phtml',
                'payment_method_icon' => $this->getSkinUrl("images/wxpay/logo.png"),
                'payment_method_label' => Mage::helper('wxpay')->getConfigData('title'),
                'payment_method_class' => $code
            ));
            
            $this->setData('_method_label_html', $labelBlock->toHtml());
        }
        return $this->getData('_method_label_html');
    }
    
    public function getMethod()
    {
        $method = $this->getData('method');
        if (!($method instanceof Mage_Payment_Model_Method_Abstract)) {
            Mage::throwException($this->__('Cannot retrieve the payment method model object.'));
        }
        return $method;
    }
    
    public function getInfoData($field)
    {
        return $this->escapeHtml($this->getMethod()->getInfoInstance()->getData($field));
    }
    /**
     * Retrieve payment configuration object
     *
     * @return Mage_Payment_Model_Config
     */
    protected function _getConfig()
    {
        return Mage::getSingleton('payment/config');
    }
    
}
