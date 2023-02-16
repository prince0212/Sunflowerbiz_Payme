<?php

namespace Sunflowerbiz\Payme\Helper;


/**
 * Class Data
 *
 * @package Sunflowerbiz\Payme\Helper
 */

use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Store\Model\ScopeInterface;
use \Sunflowerbiz\Payme\Helper\ObjectManager as Sunflowerbiz_OM;


/**
 * Class Data
 *
 * @package Sunflowerbiz\Payme\Helper
 */
class Data extends AbstractHelper
{

    /**
     *
     */
    const CLASS_DIRECTORY_LIST = '\Magento\Framework\App\Filesystem\DirectoryList';
    /**
     *
     */
    const CLASS_STOREMANAGERINTERFACE = 'Magento\Store\Model\StoreManagerInterface';


    /**
     *
     */
    const P_KEY_PATTERN = '/^pkapi\_(cert|)[\w]{5,245}$/';

    /**
     * @param $config_path
     *
     * @return string
     */
    public static function getConfig($config_path){
        return Sunflowerbiz_OM::getObjectManager()
                ->get((string) self::class)
                ->scopeConfig
                ->getValue(
                            (string) $config_path,
                            (string) ScopeInterface::SCOPE_STORE
                    );
    }
	
	

    public static function getCanSave(){
        return (int) self::getConfig(self::S_CARDS);
    }
    /**
     * @return array
     */
    public static function jsonData()    {
        return (array) json_decode((string) file_get_contents((string)'php://input'),(bool) true);
    }
    public static function getRoot()    {
        return (string) Sunflowerbiz_OM::getObjectManager()->get(self::CLASS_DIRECTORY_LIST)->getRoot();
    }
    public static function getBaseUrl()    {
        return (string) Sunflowerbiz_OM::getObjectManager()->get(self::CLASS_STOREMANAGERINTERFACE)->getStore()->getBaseUrl();;
    }



}