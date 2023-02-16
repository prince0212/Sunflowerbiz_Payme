<?php


namespace Sunflowerbiz\Payme\Observer;

use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Event\Observer;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Checkout\Model\Session;

class OrderInvoiceAfter implements ObserverInterface
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

		$_order = $observer->getEvent()->getOrder();
		$invoice = $observer->getEvent()->getInvoice();
		$orderId = $_order->getIncrementId();
		
		$directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
		$basepath  =  $directory->getRoot();
		
					
		$enable_log =$this->_scopeConfig->getValue('payment/paymepayment/enable_log', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		
						$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
						$core_write = $resource->getConnection();
						$tableName = $resource->getTableName('sun_payme_history');
						
						$selectsql = "select transaction_value from `" . $tableName . "` where order_id='" . $orderId . "' and status='SUCCESS'";
						$rows = $core_write->fetchAll($selectsql);
						$transactionId="";
						foreach($rows as $row)
							$transactionId=$row['transaction_value'];
			if($transactionId!='')
			$invoice->setTransactionId($transactionId);
		if(  $enable_log && $dumpFile = fopen($basepath .'/var/log/Payme.log', 'a+')){
														fwrite($dumpFile, "\r\n".date("Y-m-d H:i:s").' : Invoice Order: '.$orderId.' > '.$transactionId."\r\n");
		}
		return $this;
      
    }
	
}
