<?php

namespace Sunflowerbiz\Payme\Block\Adminhtml\Order;

/**
 * Payer Authentication block
 * Class Info
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class View extends \Magento\Sales\Block\Adminhtml\Order\AbstractOrder
{
    private $_objectManager;

    /**
     * View constructor.
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Sales\Helper\Admin $adminHelper
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Helper\Admin $adminHelper,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        array $data
    ) {
        parent::__construct($context, $registry, $adminHelper, $data);
        $this->_objectManager = $objectManager;
    }

    /**
     * @return array|\string[]
     */
    public function getAdditionalInformation()
    {
		$resource = $this->_objectManager->get('Magento\Framework\App\ResourceConnection');
        $coreWrite = $resource->getConnection();
        $tableName = $resource->getTableName('sun_wechat_history');

        $selectSql = "select * from `".$tableName."` where order_id='".$this->getOrder()->getRealOrderId()."' ";
        $tokenValue = $coreWrite->fetchAll($selectSql);

        return $tokenValue;
    }

}
