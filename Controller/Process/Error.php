<?php

namespace Sunflowerbiz\Payme\Controller\Process;

class Error extends \Magento\Framework\App\Action\Action 
{

    protected $_quote = false;
    protected $_checkoutSession;

    protected $_order;
    protected $_orderFactory;
    protected $_scopeConfig;
    protected $_orderHistoryFactory;


    /**
     * @param \Magento\Framework\App\Action\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context
    ) {
			// Fix for Magento2.3 adding isAjax to the request params
        if(interface_exists("\Magento\Framework\App\CsrfAwareActionInterface")) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $request = $objectManager->get('\Magento\Framework\App\Request\Http');
            
            if ( $request->isPost()) {
                $request->setParam('isAjax', true);
            }
        }
		  parent::__construct($context);
		
    }

    protected function _getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    /**
     * Set redirect
     */
    public function execute()
    {
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
		$base = $directory->getRoot();
		$active_log_stock_update = $this->_objectManager->create('Sunflowerbiz\Payme\Helper\Data')->getConfig('payment/paymepayment/enable_log');
		$postData = $this->getRequest()->getPost();
		if (sizeof($postData) <= 0) {
			$request = $this->_objectManager->get('\Magento\Framework\App\Request\Http');
			$postData = $request->getPost();
		}
		$headersdata=getallheaders();
		if(!isset($headersdata['x-client-id']) && isset($_SERVER['HTTP_X_CLIENT_ID'])){
			$headersdata=array('x-client-id'=>$_SERVER['HTTP_X_CLIENT_ID'],'x-event-type'=>$_SERVER['HTTP_X_EVENT_TYPE'],'digest'=>$_SERVER['HTTP_DIGEST'],'signature'=>$_SERVER['HTTP_SIGNATURE']);
		}
		$headers=json_encode($headersdata);
		$returndata = json_encode($postData);
		$phpinput = file_get_contents("php://input");
		if ($active_log_stock_update) {
			if ($dumpFile = fopen($base . '/var/log/Payme.log', 'a+')) {
				fwrite($dumpFile, "\r\n" . date("Y-m-d H:i:s") . ' : Notify Error data : ' . "\r\n");
				if (isset($_SERVER['REQUEST_URI'])) fwrite($dumpFile, 'Back URI->' . $_SERVER['REQUEST_URI'] . " ; " . "\r\n");
				fwrite($dumpFile, 'headers->' . ($headers) . " ; " . "\r\n");
				fwrite($dumpFile, 'postData->' . $returndata . " ; " . "\r\n");
				fwrite($dumpFile, 'phpinput->' . ($phpinput) . " ; " . "\r\n");
			}
		}
		$message=$this->_objectManager->create('\Magento\Framework\Message\ManagerInterface');
		$message->addError(__('Payment error'));
		
		 $this->_redirect('checkout/cart');
  
    }

}