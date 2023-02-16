<?php


namespace Sunflowerbiz\Payme\Observer;

use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Event\Observer;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Checkout\Model\Session;

class OrderRefundAfter implements ObserverInterface
{

 	private $_scopeConfig;

    private $orderFactory;
	private $countryFactory;
	protected $_checkoutSession;
	protected $_order;
    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
	public function __construct(
        ScopeConfigInterface $_scopeConfig,
        OrderFactory $orderFactory,
        CountryFactory $countryFactory,
        Session $checkoutSession
    ) {
        $this->_scopeConfig = $_scopeConfig;
        $this->orderFactory = $orderFactory;
        $this->countryFactory = $countryFactory;
        $this->_checkoutSession = $checkoutSession;
    }

    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
       	
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();

		$_order = $observer->getEvent()->getPayment()->getOrder();;
		$creditmemo = $observer->getEvent()->getCreditmemo();
		$orderId = $_order->getIncrementId();
		
			
		
	
		$directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
		$basepath  =  $directory->getRoot();
		
					
		
		$client_id=  $this->_scopeConfig->getValue('payment/paymepayment/client_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$client_secret=  $this->_scopeConfig->getValue('payment/paymepayment/client_secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$signing_keyid=  $this->_scopeConfig->getValue('payment/paymepayment/signing_keyid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$signing_key=  $this->_scopeConfig->getValue('payment/paymepayment/signing_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$sandbox_mode=  $this->_scopeConfig->getValue('payment/paymepayment/sandbox_mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$enable_log =$this->_scopeConfig->getValue('payment/paymepayment/enable_log', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		
		$refund =$this->_scopeConfig->getValue('payment/paymepayment/refund', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
	
		$amount=$creditmemo->getGrandTotal();
		$note=$creditmemo->getCustomerNote();
				
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
							$input->SetKV('reasonMessage',$note!=''?$note:'System Refund');
							
								
							if(  $enable_log && $dumpFile = fopen($basepath .'/var/log/Payme.log', 'a+')){
														fwrite($dumpFile, "\r\n".date("Y-m-d H:i:s").' : CreditMemo Refund Order: '.$orderId.' > '.$transactionId."\r\n");
							}
							
							$input->goRefundData();
						
							$returnArray=array();
							if($input->getKV('error')){
								$message=$objectManager->create('\Magento\Framework\Message\ManagerInterface');
								$message->addError($input->getKV('errorCode').': '.$input->getKV('errorDescription'));
								//throw new \Magento\Framework\Exception\LocalizedException($input->getKV('errorCode').': '.$input->getKV('errorDescription'));
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
