<?php
namespace Sunflowerbiz\Payme\Model\Ui;


use Magento\Store\Model\Store as Store;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Payment\Helper\Data as PaymentHelper;
/**
 * Class ConfigProvider
 */
class SunPaymeConfigProvider implements ConfigProviderInterface
{
    const CODE = 'paymepayment';

    protected $method;
    protected $_urlBuilder;
    protected $config;
	protected $_request;
	protected $_paymentHelper;


    public function __construct(
        PaymentHelper $paymentHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        Store $store
    )
    {
        $this->method[self::CODE] = $paymentHelper->getMethodInstance(self::CODE);
        $this->store = $store;
		 $this->_paymentHelper = $paymentHelper;
		  $this->_request = $request;
        $this->_urlBuilder = $urlBuilder;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'isActive' => true,
                    'redirectUrl' =>$this->_urlBuilder->getUrl('payme/process/redirect')
                ]
            ]
        ];
    }
	
	 protected function _getRequest()
    {
        return $this->_request;
    }
}
