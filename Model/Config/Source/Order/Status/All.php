<?php

namespace Sunflowerbiz\Payme\Model\Config\Source\Order\Status;
use \Magento\Sales\Model\Config\Source\Order\Status\Processing as OProcess;
class All  extends OProcess
{
	
    public function toOptionArray()
    {
		$statuses = 	$this->_orderConfig->getStatuses();
		 $options[] = ['value' => "", 'label' => ""];
        foreach ($statuses as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }
        return $options;
		
      
    }
}