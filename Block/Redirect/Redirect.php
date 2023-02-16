<?php


namespace Sunflowerbiz\Payme\Block\Redirect;
use \Sunflowerbiz\Payme\Helper\ObjectManager as Sunflowerbiz_OM;

class Redirect extends \Magento\Framework\View\Element\Template
{


    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var  \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var \Sunflowerbiz\Payme\Helper\Data
     */
    protected $_sunHelper;

    /**
     * @var ResolverInterface
     */
    protected $_resolver;

    /**
     * @var \Sunflowerbiz\Payme\Logger\SunflowerbizLogger
     */
    protected $_sunLogger;


	public $data = null;
	public $_openId = '';
 	/**
     * Redirect constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Sunflowerbiz\Payme\Helper\Data $sunHelper
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = [],
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Sunflowerbiz\Payme\Helper\Data $sunHelper,
        \Magento\Framework\Locale\ResolverInterface $resolver
    ) {
        $this->_orderFactory = $orderFactory;
        $this->_checkoutSession = $checkoutSession;
        parent::__construct($context, $data);
		
        $this->_sunHelper = $sunHelper;
        $this->_resolver = $resolver;
	
        if (!$this->_order) {
			$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $request = $objectManager->get('\Magento\Framework\App\Request\Http');
			$getincrementId=$request->getParam('incrementId');
			if($getincrementId!='')
			$incrementId = $getincrementId;
			else
			$incrementId = $this->_getCheckout()->getLastRealOrderId();
			
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }
		
    }
	
	public function getOrderId(){
		$incrementId=$this->_order->getIncrementId();
		return $incrementId;
	}
	
	public function getLogoimage(){
		$logo=  $this->_scopeConfig->getValue('payment/paymepayment/logo', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
		$baseurl=$storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
		$mediapath=$storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
		if($logo!="")
		return $mediapath.'/sunflowerbiz/payme/'.$logo;
		else
		return "";
	}
	
	public function getPaymeLogoimage(){
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
		$baseurl=$storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
		$mediapath=$storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
	
		return $mediapath.'/sunflowerbiz/payme/payme_logo_color_oneline_x.png';
	}
	
	 public function getQrimage()
    {
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
		$basepath  =  $directory->getRoot();
   		//date_default_timezone_set('Asia/Shanghai');
		
        $input =Sunflowerbiz_OM::getObjectManager()->create('\Sunflowerbiz\Payme\Model\Payme');
		
		$client_id=  $this->_scopeConfig->getValue('payment/paymepayment/client_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$client_secret=  $this->_scopeConfig->getValue('payment/paymepayment/client_secret', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$signing_keyid=  $this->_scopeConfig->getValue('payment/paymepayment/signing_keyid', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$signing_key=  $this->_scopeConfig->getValue('payment/paymepayment/signing_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$sandbox_mode=  $this->_scopeConfig->getValue('payment/paymepayment/sandbox_mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$enable_log =$this->_scopeConfig->getValue('payment/paymepayment/enable_log', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		
		$storename=$this->_storeManager->getStore()->getName();
		$orderid=$this->_order->getIncrementId();
		$ordertotal=round($this->_order->getGrandTotal(),2);
		$orderCurrencyCode = $this->_order->getOrderCurrencyCode();
		$notificationUri=$this->_urlBuilder->getUrl('payme/process/notify');
		$appSuccessCallback=$this->_urlBuilder->getUrl('checkout/onepage/success');
		$appFailCallback=$this->_urlBuilder->getUrl('payme/process/error');
		
		$input->SetKV('error',false);
		$input->SetKV('enable_log',$enable_log);
		$input->SetKV('basepath',$basepath);
		$input->SetKV('sandbox_mode',$sandbox_mode);
		
		$input->SetKV('client_id',$client_id);
		$input->SetKV('client_secret',$client_secret);
		$input->SetKV('signing_keyid',$signing_keyid);
		$input->SetKV('signing_key',$signing_key);
		
		$input->SetKV('orderId',$orderid);
		$input->SetKV('totalAmount',$ordertotal);
		$input->SetKV('currencyCode',$orderCurrencyCode);
		$input->SetKV('notificationUri',$notificationUri);
		$input->SetKV('appSuccessCallback',$appSuccessCallback);
		$input->SetKV('appFailCallback',$appFailCallback);
		$input->SetKV('effectiveDuration',30);
		
		$input->goPaymentData();
		if($input->getKV('error')){
			return '<div id="errormsg">'.$input->getKV('errorCode').': '.$input->getKV('errorDescription').'</div>';
		}else{
			return '<div id="qrcodedata">'.($this->is_mobile_request()?$input->getKV('appLink'):$input->getKV('webLink')).'</div>';
		}
			
		
    }
	
	
	public function getSanEventUrl()
    {
        $url= $this->_urlBuilder->getUrl("payme/process/scanevent");
		if(substr($url,-1)=='/')  $url=substr($url,0,strlen($url)-1); 
		return $url;
    }

  	 public function getFailureUrl()
    {
        return $this->_urlBuilder->getUrl("payme/process/error");
    }

 	 public  function getSuccessUrl()
    {
        return $this->_urlBuilder->getUrl("checkout/onepage/success");
    }

	public function getContinueUrl(){
		return $this->_urlBuilder->getUrl();
	}
	
    /**
     * @return $this
     */
    public function _prepareLayout()
    {
		
        return parent::_prepareLayout();
    }

   
    /**
     * @return mixed
     */
    public function getPaymentMethodSelectionOnSunflowerbiz()
    {
        return $this->_sunHelper->getSunflowerbizHppConfigDataFlag('payment_selection_on_sun');
    }


 	public function is_mobile_request()  
	{  
	 $_SERVER['ALL_HTTP'] = isset($_SERVER['ALL_HTTP']) ? $_SERVER['ALL_HTTP'] : '';  
	 $mobile_browser = '0';  
	 if(preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|iphone|ipad|ipod|android|xoom)/i', strtolower($_SERVER['HTTP_USER_AGENT'])))  
	  $mobile_browser++;  
	 if((isset($_SERVER['HTTP_ACCEPT'])) and (strpos(strtolower($_SERVER['HTTP_ACCEPT']),'application/vnd.wap.xhtml+xml') !== false))  
	  $mobile_browser++;  
	 if(isset($_SERVER['HTTP_X_WAP_PROFILE']))  
	  $mobile_browser++;  
	 if(isset($_SERVER['HTTP_PROFILE']))  
	  $mobile_browser++;  
	 $mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'],0,4));  
	 $mobile_agents = array(  
		'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',  
		'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',  
		'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',  
		'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',  
		'newt','noki','oper','palm','pana','pant','phil','play','port','prox',  
		'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',  
		'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-',  
		'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',  
		'wapr','webc','winw','winw','xda','xda-'
		);  
	 if(in_array($mobile_ua, $mobile_agents))  
	  $mobile_browser++;  
	 if(strpos(strtolower($_SERVER['ALL_HTTP']), 'operamini') !== false)  
	  $mobile_browser++;  
	 // Pre-final check to reset everything if the user is on Windows  
	 if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows') !== false)  
	  $mobile_browser=0;  
	 // But WP7 is also Windows, with a slightly different characteristic  
	 if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows phone') !== false)  
	  $mobile_browser++;  
	 if($mobile_browser>0)  
	  return true;  
	 else
	  return false;
	}
    
    /**
     * @param null $date
     * @param string $format
     * @return mixed
     */
    protected function _getDate($date = null, $format = 'Y-m-d H:i:s')
    {
        if (strlen($date) < 0) {
            $date = date('d-m-Y H:i:s');
        }
        $timeStamp = new \DateTime($date);
        return $timeStamp->format($format);
    }

    /**
     * The character escape function is called from the array_map function in _signRequestParams
     *
     * @param $val
     * @return mixed
     */
    protected function escapeString($val)
    {
        return str_replace(':', '\\:', str_replace('\\', '\\\\', $val));
    }

    /**
     * Get frontend checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckout()
    {
        return $this->_checkoutSession;
    }
}