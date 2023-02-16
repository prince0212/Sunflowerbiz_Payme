<?php

namespace Sunflowerbiz\Payme\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
  $tableName = $installer->getTable('sun_payme_history');
        // Check if the table already exists
        if ($installer->getConnection()->isTableExists($tableName) != true) {


            // Create tutorial_simplenews table
            $table = $installer->getConnection()
                ->newTable($tableName)
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true
                    ],
                    'record id'
                )
                ->addColumn(
                    'create_time',
                    Table::TYPE_DATETIME,
                    null,
                    [],
                    'create_time'
                )
                ->addColumn(
                    'order_id',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'order_id'
                )
                ->addColumn(
                    'transaction_value',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    'transaction value'
                )
				->addColumn(
					'data',
					\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
					null,
					['nullable' => true,'default' => ''],
					'payment data'
				)
                ->addColumn(
                    'status',
                    Table::TYPE_TEXT,
                    255,
                    ['nullable' => false],
                    '1:complete;0:canceled'
                )
              ;
            $installer->getConnection()->createTable($table);
        }

        $installer->endSetup();
    }
} //sun_vault/Setup/InstallSchema.php