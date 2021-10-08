<?php
/**
 * @package        Joomla
 * @subpackage     EShop Shopping Cart
 * @author         Giang Dinh Truong
 * @copyright      Copyright (C) 2012 - 2017 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

//require dirname(__FILE__) . '/ECPay.Payment.Integration.php';
include('ECPay.Payment.Integration.php');

class os_ecpay extends os_payment
{
	/**
	 * Payment class object
	 *
	 * @var classSpg
	 */
	//protected $MPG;


	public function __construct($params, $config = array())
	{
    	$config = array(
    		'type' => 0,
    		'showATM' => false,
    	//	'show_card_holder_name' => false
    	);
    	parent::__construct($params, $config);

	}

	/**
	 * Process Payment
	 *
	 * @param array $data        	
	 */
	public function processPayment($data)
	{
		$siteUrl = JUri::base();

        $obj = new ECPay_AllInOne();
        
        $status = $this->params->get('mode');
        
        if($status == 1){
            $ecpayURL = "https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5";
            $hashkey = $this->params->get('hashkey');
			$hashIV = $this->params->get('hashiv');
			$MerchID = $this->params->get('MerchantID');
        }
        else{
            $ecpayURL = "https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5";
            $hashkey = '5294y06JbISpM5x9';
			$hashIV = 'v77hoKGq4kWxNNIS';   
			$MerchID = '2000132';
        }
        
        $obj->ServiceURL  = $ecpayURL;  //服務位置
        $obj->HashKey     = $hashkey;   //測試用Hashkey，請自行帶入ECPay提供
        $obj->HashIV      = $hashIV ;   //測試用HashIV，請自行帶入ECPay提供的
        $obj->MerchantID  = $MerchID;   //測試用MerchantID，請自行帶入ECPay提供的
        $obj->EncryptType = '1';        //CheckMacValue加密類型，請固定填入1，使用SHA256加密
        
        $MerchantTradeNo = $data['order_number'];
       
        $obj->Send['ClientBackURL'] = $siteUrl; 
        $obj->Send['ReturnURL']  = $siteUrl .'index.php?option=com_eshop&task=checkout.verifyPayment&payment_method=os_ecpay&type=notify';   //付款完成通知回傳的網址
        $obj->Send['OrderResultURL'] = $siteUrl . 'index.php?option=com_eshop&view=checkout&layout=complete&order_number='. $data['order_number'];     //付款完成通知回傳的網址
        $obj->SendExtend['ClientRedirectURL']   = $siteUrl . 'index.php?option=com_eshop&view=checkout&layout=complete&order_number='. $data['order_number'];   
             //訂單編號
        $obj->Send['MerchantTradeNo']   = $MerchantTradeNo;                           //訂單編號
        $obj->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');                        //交易時間
        $obj->Send['TotalAmount']       = round($data['total']);                       //交易金額
        $obj->Send['PaymentType']       = 'aio';                                      
        $obj->Send['TradeDesc']         = JText::sprintf('ESHOP_PAYMENT_FOR_ORDER', $data['order_number']);                           //交易描述
      //  $obj->Send['ChoosePayment']     = ECPay_PaymentMethod::ATM ;  //付款方式:全功能
        $obj->Send['ChoosePayment']     = ECPay_PaymentMethod::All;  //付款方式:全功能
     // $obj->SendExtend['IgnorePayment']     = 'WebATM#BARCODE#CVS';
        $obj->SendExtend['ExpireDate']     = 5 ;       
        //訂單的商品資料
        array_push($obj->Send['Items'], array(
            'Name' => $siteUrl. "商品", 
            'Price' => $obj->Send['TotalAmount'],
            'Currency' => "元", 
            'Quantity' => (int) "1", 
            'URL' => ""));
        //產生訂單(auto submit至ECPay)
        $obj->CheckOut();
	}
	/**
	 * Verify payment
	 *
	 * @return void
	 */
  
	public function verifyPayment()
	{
		$app  = JFactory::getApplication();
		$type = $app->input->getString('type');
		$row = JTable::getInstance('Eshop', 'Order');
        $status = $this->params->get('mode');
        
        if($status == 1){
            $ecpayURL = "https://payment.ecpay.com.tw/Cashier/QueryTradeInfo/V5";
            $hashkey = $this->params->get('hashkey');
			$hashIV = $this->params->get('hashiv');
			$MerchID = $this->params->get('MerchantID');
        }
        else{
            $ecpayURL = "https://payment-stage.ecpay.com.tw/Cashier/QueryTradeInfo/V5";
            $hashkey = '5294y06JbISpM5x9';
			$hashIV = 'v77hoKGq4kWxNNIS';   
			$MerchID = '2000132';
        }
        $AL = new ECPay_AllInOne();
        $AL->ServiceURL =  $ecpayURL;
        $AL->MerchantID = $MerchID;   
        $AL->HashKey = $hashkey;        
        $AL->HashIV = $hashIV ;       
        $AL->EncryptType = ECPay_EncryptType::ENC_SHA256; // SHA256   
        $szMessage = '1|OK';
        try {
            // 取得回傳參數。
            $checkout_feedback = $AL->CheckOutFeedback();
            $OrderID = $checkout_feedback['MerchantTradeNo'];
            $db = JFactory::getDbo();
            $db->setQuery("SELECT id FROM #__eshop_orders  WHERE order_number='$OrderID'");
            $ida = $db->loadResult();
            // 檢核與變更訂單狀態。
            if (sizeof($checkout_feedback) > 0) {
                $row->load($ida);   
                $ZWTDAmount =  floatval($row->total);
                $ECpayAmount = $checkout_feedback['TradeAmt'];
                $ECPayID = $checkout_feedback['TradeNo'];
                $szReturnCode = $checkout_feedback['RtnCode'];
                $szReturnMessage = $checkout_feedback['RtnMsg'];
                // 核對訂單金額。
                if ($ECpayAmount == $ZWTDAmount) {
                    // 當訂單回傳狀態為無異常，更新訂單資料與新增訂單歷程。
                    if ($szReturnCode == 1 || $szReturnCode == 2 || $szReturnCode == 800) {
                        // 更新訂單資料與新增訂單歷程。$id
                        $row->payment_date = $checkout_feedback['PaymentDate'];
                        $row->transaction_id = $ECPayID;
                        $row->payment_eu_vat_number =  $checkout_feedback['PaymentType'];
                        $params = new JRegistry($row->params);
                    	$params->set('paymenttype', $checkout_feedback['PaymentType']);
                    	//$params->set('returnmessage', $szReturnMessage);
                    	$params->set('returncode', $szReturnCode);
                    	$params->set('chargefee', $checkout_feedback['PaymentTypeChargeFee']);
                    	$params->set('tradedate', $checkout_feedback['TradeDate']);
                    	$params->set('macvalue', $checkout_feedback['CheckMacValue']);
                    	$row->params = $params->toString();
                        $row->order_status_id =  15;
                        $row->store();
                        EshopHelper::completeOrder($row);
                        JPluginHelper::importPlugin('eshop');
                        $dispatcher = JDispatcher::getInstance();
                        $dispatcher->trigger('onAfterCompleteOrder', array($row));
                        
                        //Send confirmation email here
                        if (EshopHelper::getConfigValue('order_alert_mail'))
                        {
                            EshopHelper::sendEmails($row);
                        }
                    } else {
                        throw new Exception("Order '$OrderID' Exception.($szReturnCode: $szReturnMessage)");
                    }
                } else {
                    throw new Exception("0|Compare '$OrderID' Order Amount Fail.");
                }
            } else {
                throw new Exception("Order('$OrderID') Not Found at ECPay.");
            }
        } catch (Exception $e) {
            // 背景訊息
            $szMessage = '0|' . $e->getMessage();
        }
       echo $szMessage;
       exit;
        //return true;
	}

}
