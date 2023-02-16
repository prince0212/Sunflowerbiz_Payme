<?php

namespace Sunflowerbiz\Payme\Controller\Process;

class Scanevent extends \Magento\Framework\App\Action\Action 
{

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $_quote = false;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;
    protected $_scopeConfig;
    protected $_orderHistoryFactory;


    /**
     * @param \Magento\Framework\App\Action\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context
    ) {
	
		
        parent::__construct($context);
		
    }

    /**
     * Return checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    /**
     * Set redirect
     */
    public function execute()
    {
	
	    $orderId = $this->getRequest()->getParam('orderId') ;
		
		
	    $action = $this->getRequest()->getParam('action') ;
	    $amount = $this->getRequest()->getParam('amount') ;
	    $transaction_value = $this->getRequest()->getParam('transaction_value') ;
		
		if($action=='refund' && $orderId){
			$this->getResponse()->setBody($this->Refund($orderId,$transaction_value,$amount));
			return;
		}
		
        $responses = array('status' => 'no', 'message' => '');
        $CanPay = true;
        if ($orderId) {
			
			
			$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
			$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
			$core_write = $resource->getConnection();
			$tableName = $resource->getTableName('sun_payme_history');
			
			$transaction_value="";
				$selectsql= "select * from `".$tableName."` where order_id='".$orderId."' and status='SUCCESS'";
				$payme_history=$core_write->fetchAll($selectsql);
				if(count($payme_history)>0){			
						foreach($payme_history as $history)
							$transaction_value=$history['transaction_value'];			
				}
				
				if($transaction_value!='')
				 $responses = array('status'=>'ok','message' => __('Order Paied success.') );
				

		}
		$this->getResponse()->setBody(json_encode($responses));

		
		return;
    }
	function Refund($orderId,$transaction_value,$amount){
	
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();

	
		$directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
		$basepath  =  $directory->getRoot();
		
		$_order = $objectManager->create('\Magento\Sales\Model\Order')->loadByIncrementId($orderId);
		$_scopeConfig=$objectManager->create('\Magento\Framework\App\Config\ScopeConfigInterface');
		$client_id=  $_scopeConfig->getValue('payment/paymepayment/client_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$client_secret=  $_scopeConfig->getValue('payment/paymepayment/client_secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$signing_keyid=  $_scopeConfig->getValue('payment/paymepayment/signing_keyid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$signing_key=  $_scopeConfig->getValue('payment/paymepayment/signing_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$sandbox_mode=  $_scopeConfig->getValue('payment/paymepayment/sandbox_mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$enable_log =$_scopeConfig->getValue('payment/paymepayment/enable_log', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		
		$refund =$_scopeConfig->getValue('payment/paymepayment/refund', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		
		if($orderId!="" && $refund  ){
		
						//do refund
						$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
						$core_write = $resource->getConnection();
						$tableName = $resource->getTableName('sun_payme_history');
						
						$selectsql = "select transaction_value from `" . $tableName . "` where order_id='" . $orderId . "' and status='SUCCESS'";
						$rows = $core_write->fetchAll($selectsql);
						foreach($rows as $row)
							$transactionId=$row['transaction_value'];
						
						if($transaction_value!=$transactionId) return 'Error';
						
						$selectsql = "select * from `" . $tableName . "` where order_id='" . $orderId . "' and status='REFUNDED'";
						$payme_history = $core_write->fetchAll($selectsql);
						
						if (count($payme_history) <= 0 && $transactionId!="") {
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
							$input->SetKV('totalAmount',$amount>0?$amount:$ordertotal);
							$input->SetKV('currencyCode',$orderCurrencyCode);
							$input->SetKV('reasonCode','00');
							$input->SetKV('reasonMessage','System Refund');
							
								
							if(  $enable_log && $dumpFile = fopen($basepath .'/var/log/Payme.log', 'a+')){
														fwrite($dumpFile, "\r\n".date("Y-m-d H:i:s").' : Refund Order: '.$orderId.' > '.$transactionId."\r\n");
							}
							
							$input->goRefundData();
						
							$returnArray=array();
							if($input->getKV('error')){
							
							
								return ($input->getKV('errorCode').': '.$input->getKV('errorDescription'));
								//throw new \Magento\Framework\Exception\LocalizedException($input->getKV('errorCode').': '.$input->getKV('errorDescription'));
								
							}elseif($input->getKV('refundId')!=''){
							$returnArray=$input->getKV('ReturnRefundRequest');
							$transactionId=isset($returnArray['refundId'])?$returnArray['refundId']:$transactionId;
							$insertsql = "insert into `" . $tableName . "` (create_time,order_id,transaction_value,data,status) values (now(),'" . $orderId . "','" . $transactionId . "','" . json_encode($returnArray ). "','REFUNDED')";
							$core_write->query($insertsql);
							return __('Refunded Successfully');
							
							}
							
						}
						
						
				
							
		
		}
		
	}

}