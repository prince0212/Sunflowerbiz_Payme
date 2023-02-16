<?php

namespace Sunflowerbiz\Payme\Controller\Process;

class Notify extends \Magento\Framework\App\Action\Action 
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
	
		
		// Fix for Magento2.3 adding isAjax to the request params
        if(interface_exists("\Magento\Framework\App\CsrfAwareActionInterface")) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $request = $objectManager->get('\Magento\Framework\App\Request\Http');
            
            if ( $request->isPost()) {
                $request->setParam('isAjax', true);
                if( empty( $request->getParam('form_key') ) ){
					$formKey = $objectManager->get(\Magento\Framework\Data\Form\FormKey::class);
              	 	$request->setParam('form_key', $formKey->getFormKey());
				}
            }
        }
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
		$directory = $this->_objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
		$base = $directory->getRoot();
		$this->_orderHistoryFactory = $this->_objectManager->get('\Magento\Sales\Model\Order\Status\HistoryFactory');
		$active_log_stock_update = $this->_objectManager->create('Sunflowerbiz\Payme\Helper\Data')->getConfig('payment/paymepayment/enable_log');
		$order_status_payment_accepted = $this->_objectManager->create('Sunflowerbiz\Payme\Helper\Data')->getConfig('payment/paymepayment/order_status_payment_accepted');
		$postData = $this->getRequest()->getPost();
		if (sizeof($postData) <= 0) {
			$request = $this->_objectManager->get('\Magento\Framework\App\Request\Http');
			$postData = $request->getPost();
		}
		$headersdata=getallheaders();
		if(!isset($headersdata['x-client-id']) && isset($_SERVER['HTTP_X_CLIENT_ID'])){
			$headersdata=array('x-client-id'=>$_SERVER['HTTP_X_CLIENT_ID'],'x-event-type'=>$_SERVER['HTTP_X_EVENT_TYPE'],'digest'=>$_SERVER['HTTP_DIGEST'],'signature'=>$_SERVER['HTTP_SIGNATURE']
			,'(request-target)'=>'post /'.trim($_SERVER['REQUEST_URI'],'/').'/');
		}
		if(!isset($headersdata['(request-target)']))$headersdata['(request-target)']='post /'.trim($_SERVER['REQUEST_URI'],'/').'/';
		
		$headers=json_encode($headersdata);
		$returndata = json_encode($postData);
		$phpinput = file_get_contents("php://input");
		if ($active_log_stock_update) {
			if ($dumpFile = fopen($base . '/var/log/Payme.log', 'a+')) {
				fwrite($dumpFile, "\r\n" . date("Y-m-d H:i:s") . ' : Notify data : ' . "\r\n");
				if (isset($_SERVER['REQUEST_URI'])) fwrite($dumpFile, 'Back URI->' . $_SERVER['REQUEST_URI'] . " ; " . "\r\n");
				fwrite($dumpFile, 'headers->' . ($headers) . " ; " . "\r\n");
				fwrite($dumpFile, 'postData->' . $returndata . " ; " . "\r\n");
				fwrite($dumpFile, 'phpinput->' . ($phpinput) . " ; " . "\r\n");
			}
		}
		
		
		
		$jsoninput=(json_decode($phpinput,true));
		if(!is_null($jsoninput)) $returnArray=json_decode($phpinput,true);
		else $returnArray=$postData;

		
		$statusDescription=isset($returnArray['statusDescription'])?$returnArray['statusDescription']:'';
		$orderId=isset($returnArray['transactions'][0]['orderId'])?$returnArray['transactions'][0]['orderId']:'';
		$transactionId=isset($returnArray['transactions'][0]['transactionId'])?$returnArray['transactions'][0]['transactionId']:'';
		$totalAmount=isset($returnArray['totalAmount'])?$returnArray['totalAmount']:'';
	
		if ($active_log_stock_update) {
			if ($dumpFile = fopen($base . '/var/log/Payme.log', 'a+')) {
					fwrite($dumpFile, 'checkSign->' . $this->checkSign($headersdata,$phpinput)  . " ; orderId " .$orderId. " ; totalAmount " .$totalAmount. " ; transactionId " .$transactionId. "\r\n");
			}
		}
	
		
		if ($this->checkSign($headersdata,$phpinput) && isset($headersdata['x-event-type']) &&  $headersdata['x-event-type'] == 'payment.success'  && $orderId!="" ) {
			$incrementId = $orderId;
			$order = $this->_getOrder($incrementId);
			$comment = "Payment Done.";
			//$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
			$order = $this->_objectManager->create('\Magento\Sales\Model\Order')->loadByIncrementId($incrementId);
			$order->setState($order_status_payment_accepted)->setStatus($order_status_payment_accepted);
			//  $order->setTotalPaid($order->getGrandTotal());
			$this->getResponse()->setBody("success");
			
			$resource = $this->_objectManager->get('Magento\Framework\App\ResourceConnection');
			$core_write = $resource->getConnection();
			$tableName = $resource->getTableName('sun_payme_history');
			$selectsql = "select * from `" . $tableName . "` where order_id='" . $incrementId . "' and status='SUCCESS'";
			$payme_history = $core_write->fetchAll($selectsql);
			if (count($payme_history) <= 0) {
				$order->save();
				$history = $this->_orderHistoryFactory->create()->setStatus($order_status_payment_accepted)->setComment($comment)->setEntityName('order')->setOrder($order);
				$history->save();
				$insertsql = "insert into `" . $tableName . "` (create_time,order_id,transaction_value,data,status) values (now(),'" . $incrementId . "','" . $transactionId . "','" . json_encode($returnArray ). "','SUCCESS')";
				$core_write->query($insertsql);
				
				$this->createTransaction($order, $transactionId, $totalAmount, json_encode($returnArray));
			}
			// $this->_redirect('checkout/onepage/success');
				$this->getResponse()->setBody("success");
				
		}
		return;
		
	}

	 protected function checkSign($headersdata,$phpinput)
    {
		$signing_key = $this->_objectManager->create('Sunflowerbiz\Payme\Helper\Data')->getConfig('payment/paymepayment/signing_key');
        $input =$this->_objectManager->create('\Sunflowerbiz\Payme\Model\Payme');
		$headersignature= isset($headersdata['signature'])?$headersdata['signature']:'';
		
		$headersignatureArr=$this->getsigndata($headersignature);
		$headersignatureArrHeaders=isset($headersignatureArr['headers'])?$headersignatureArr['headers']:"";
		$headerHash=array();
		
		foreach(explode(' ',$headersignatureArrHeaders) as $headertag){
			if(isset($headersdata[$headertag]))
			$headerHash[$headertag]=$headersdata[$headertag];
		}
		$signingBase="";
		foreach($headerHash as $h=>$hh){
			if ($signingBase !== '')$signingBase .= "\n";
			if($h=='(request-target)')$headerHash[$h]=trim($headerHash[$h],'/').'/';
			$signingBase .= strtolower($h).': '.$headerHash[$h];
		}
		
		$signaturehash=$input->base64hash256($signingBase,$signing_key,true);
		
	
		$givenSign=isset($headersignatureArr['signature'])?$headersignatureArr['signature']:'';
		
		/*
		$directory = $this->_objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
		$base = $directory->getRoot();
			if ($dumpFile = fopen($base . '/var/log/Payme.log', 'a+')) {
				fwrite($dumpFile, "\r\n" . date("Y-m-d H:i:s") . ' : checkSign data : ' . "\r\n");
				fwrite($dumpFile, 'headersdata->' . json_encode($headersdata) . " ; " . "\r\n");
				fwrite($dumpFile, 'phpinput->' . ($phpinput) . " ; " . "\r\n");
				fwrite($dumpFile, 'signingBase->' . ($signingBase) . " ; " . "\r\n");
				fwrite($dumpFile, 'signaturehash->' . ($signaturehash) . " ; " . "\r\n");
				fwrite($dumpFile, 'givenSign->' . ($givenSign) . " ; " . "\r\n");
			}*/
		
		if($givenSign!="" &&  $givenSign == $signaturehash)
		return true;
		else
        return false;
    }
	
	 private function createTransaction($order, $transactionId, $paymentAmount, $paymentData)
    {
        try {
            $payment = $order->getPayment();
            $payment->setLastTransId($transactionId);
            $payment->setTransactionId($transactionId);
            $payment->setAdditionalInformation(
                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS =>  $paymentData]
            );
            $formatedPrice = $order->getBaseCurrency()->formatTxt(
            //$order->getGrandTotal()
                $paymentAmount
            );

            $message = __('Payment amount is %1.', $formatedPrice);
            
			$trans = $this->_objectManager->get('\Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface');
            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($transactionId)
                ->setAdditionalInformation(
                    [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS =>  $paymentData]
                )
                ->setFailSafe(true)
                ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_PAYMENT);

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId(null);
            $payment->save();
//            $order->save();

            return  $transaction->save()->getTransactionId();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
	
	protected function getsigndata($signature){
		$data=array();
		$data1=explode(",",$signature);
		foreach($data1 as $data1d){
			$data1d2=explode('"',$data1d);
			if(isset($data1d2[1]))
			$data[str_replace('=','',$data1d2[0])]=str_replace('"','',$data1d2[1]);
		}
		return ($data);
	}

    /**
     * Get order object
     *
     * @return \Magento\Sales\Model\Order
     */
    protected function _getOrder()
    {
        if (!$this->_order) {
            $incrementId = $this->_getCheckout()->getLastRealOrderId();
            $this->_orderFactory = $this->_objectManager->get('Magento\Sales\Model\OrderFactory');
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }
        return $this->_order;
    }

    /**
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckout()
    {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }

    /**
     * @return mixed
     */
    protected function _getQuote()
    {
        return $this->_objectManager->get('Magento\Quote\Model\Quote');
    }

    /**
     * @return mixed
     */
    protected function _getQuoteManagement()
    {
        return $this->_objectManager->get('\Magento\Quote\Model\QuoteManagement');
    }
}