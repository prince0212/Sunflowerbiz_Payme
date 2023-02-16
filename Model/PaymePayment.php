<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sunflowerbiz\Payme\Model;



/**
 * Pay In Store payment method model
 */
class PaymePayment extends \Magento\Payment\Model\Method\AbstractMethod
{

	 const CODE       = 'paymepayment';
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'paymepayment';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;
 

	protected $_isInitializeNeeded = true;

	protected $_canRefund = true;
	
    protected $_canRefundInvoicePartial = true;

  	public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
		$basepath  =  $directory->getRoot();
		$_order = $payment->getOrder();;
		$orderId = $_order->getIncrementId();
		
		
				
		$message=$objectManager->create('\Magento\Framework\Message\ManagerInterface');
		
		$directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
		$basepath  =  $directory->getRoot();
		
		$_scopeConfig=$objectManager->create('\Magento\Framework\App\Config\ScopeConfigInterface');
		
		$client_id=  $_scopeConfig->getValue('payment/paymepayment/client_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$client_secret=  $_scopeConfig->getValue('payment/paymepayment/client_secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$signing_keyid=  $_scopeConfig->getValue('payment/paymepayment/signing_keyid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$signing_key=  $_scopeConfig->getValue('payment/paymepayment/signing_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$sandbox_mode=  $_scopeConfig->getValue('payment/paymepayment/sandbox_mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$enable_log =$_scopeConfig->getValue('payment/paymepayment/enable_log', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		
		$refund =$_scopeConfig->getValue('payment/paymepayment/refund', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

		if($orderId!="" && $refund ){
		
					$orderStatus=$_order->getStatus();
				
						//check if refuned
						
						//do refund
						$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
						$core_write = $resource->getConnection();
						$tableName = $resource->getTableName('sun_payme_history');
						
						$selectsql = "select transaction_value from `" . $tableName . "` where order_id='" . $orderId . "' and status='SUCCESS'";
						$rows = $core_write->fetchAll($selectsql);
						$transactionId="";
						foreach($rows as $row)
							$transactionId=$row['transaction_value'];
						
						
						$selectsql = "select * from `" . $tableName . "` where order_id='" . $orderId . "' and status='REFUND'";
						$payme_history = $core_write->fetchAll($selectsql);
						
						if ( $transactionId!="") {
							//$orderFactory=$objectManager->create('\Magento\Sales\Model\OrderFactory');
							//$_order= $orderFactory->create()->loadByIncrementId($orderId);
							$input =$objectManager->create('\Sunflowerbiz\Payme\Model\Payme');
							$ordertotal=round($_order->getGrandTotal(),2);
							$orderCurrencyCode = $_order->getOrderCurrencyCode();
							$input->SetKV('error',false);
							$input->SetKV('enable_log',$enable_log);
							$input->SetKV('basepath',$basepath);
							$input->SetKV('sandbox_mode',$sandbox_mode);
							$input->SetKV('transactionId',$transactionId);
							
							$input->SetKV('client_id',$client_id);
							$input->SetKV('client_secret',$client_secret);
							$input->SetKV('signing_keyid',$signing_keyid);
							$input->SetKV('signing_key',$signing_key);
							
							$input->SetKV('orderId',$orderId);
							$input->SetKV('totalAmount',$amount>0?$amount:0);
							$input->SetKV('currencyCode',$orderCurrencyCode);
							$input->SetKV('reasonCode','00');
							$input->SetKV('reasonMessage','System Refund');
							
								
							if(  $enable_log && $dumpFile = fopen($basepath .'/var/log/Payme.log', 'a+')){
														fwrite($dumpFile, "\r\n".date("Y-m-d H:i:s").' : CreditMemo Online Refund Order: '.$orderId.' > '.$transactionId."\r\n");
							}
							
							$input->goRefundData();
						
							$returnArray=array();
							if($input->getKV('error')){
								$message=$objectManager->create('\Magento\Framework\Message\ManagerInterface');
								//$message->addError($input->getKV('errorCode').': '.$input->getKV('errorDescription'));
								throw new \Magento\Framework\Exception\LocalizedException(__($input->getKV('errorCode').': '.$input->getKV('errorDescription')));
								return '';
							}elseif($input->getKV('refundId')!=''){
								$returnArray=$input->getKV('ReturnRefundRequest');
								$insertsql = "insert into `" . $tableName . "` (create_time,order_id,transaction_value,data,status) values (now(),'" . $orderId . "','" . $transactionId . "','" . json_encode($returnArray ). "','REFUND')";
								$core_write->query($insertsql);
								$message=$objectManager->create('\Magento\Framework\Message\ManagerInterface');
								$message->addSuccess('Payme Refunded Successfully');
								return '';
							}
							
						}
						
						
					
							
		
		}	   
        return $this;
    }

  
}
