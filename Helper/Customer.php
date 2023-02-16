<?php

namespace Sunflowerbiz\Payme\Helper;
use \Sunflowerbiz\Payme\Helper\ObjectManager as Sunflowerbiz_OM;

/**
 * Class Customer
 *
 * @package Sunflowerbiz\Payme\Helper
 */
class Customer
{
    /**
     * @return \Magento\Customer\Model\Session
     */
    public static function getSession(){
        return Sunflowerbiz_OM::getObjectManager()->get('Magento\Customer\Model\Session');
    }

    /**
     * @return bool
     */
    public static function isLoggedIn(){

        return self::getSession()->isLoggedIn();
    }

    /**
     * @return bool|int
     */
    public static function getCustID(){
        return self::isLoggedIn() ? (int)self::getSession()->getCustomerId() : false;
    }

}