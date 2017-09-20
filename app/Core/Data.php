<?php
namespace PentagonalProject\Client17Ir\Core;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

/**
 * Class Data
 * @package PentagonalProject\Client17Ir\Core
 */
class Data
{
    const TABLE_NAME = 'ir_whois_data';

    const COLUMN_ID = 'id';
    const COLUMN_DOMAIN_NAME = 'ir_domain_name';
    const COLUMN_DATE_CREATED = 'date_created';
    const COLUMN_COM = 'is_com';
    const COLUMN_NET = 'is_net';
    const COLUMN_ORG = 'is_org';
    const COLUMN_INFO = 'is_info';
    const COLUMN_BACK_LINK = 'ir_back_link';
    const COLUMN_COM_RANK = 'com_rank';
    const COLUMN_NET_RANK = 'net_rank';
    const COLUMN_ORG_RANK = 'org_rank';
    const COLUMN_INFO_RANK = 'info_rank';

    /**
     * Cache constructor.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * initial
     */
    protected function init()
    {
        $db = DI::get(Db::class);
        if (!$db->tablesExist(self::TABLE_NAME)) {
            $table = new Table(self::TABLE_NAME);
            $table
                ->addColumn(self::COLUMN_ID, Type::BIGINT)
                ->setAutoincrement(true)
                ->setNotnull(true)
                ->setLength(10);
            $table
                ->addColumn(self::COLUMN_DOMAIN_NAME, Type::STRING)
                ->setNotnull(true)
                ->setLength(255);
            $table
                ->addColumn(self::COLUMN_DATE_CREATED, Type::DATE)
                ->setNotnull(true)
                ->setDefault('1990-00-00 00:00:00');
            $table
                ->addColumn(self::COLUMN_COM, Type::BOOLEAN)
                ->setNotnull(false)
                ->setDefault(null);
            $table
                ->addColumn(self::COLUMN_NET, Type::BOOLEAN)
                ->setNotnull(false)
                ->setDefault(null);
            $table
                ->addColumn(self::COLUMN_ORG, Type::BOOLEAN)
                ->setNotnull(false)
                ->setDefault(null);
            $table
                ->addColumn(self::COLUMN_INFO, Type::BOOLEAN)
                ->setNotnull(false)
                ->setDefault(null);
            $table
                ->addColumn(self::COLUMN_BACK_LINK, Type::TEXT)
                ->setNotnull(true)
                ->setDefault('')
                ->setLength(16777216); # 16MB
            $table
                ->addColumn(self::COLUMN_COM_RANK, Type::BIGINT)
                ->setNotnull(false)
                ->setDefault(0)
                ->setLength(10);
            $table
                ->addColumn(self::COLUMN_NET_RANK, Type::BIGINT)
                ->setNotnull(false)
                ->setDefault(0)
                ->setLength(10);
            $table
                ->addColumn(self::COLUMN_ORG_RANK, Type::BIGINT)
                ->setNotnull(false)
                ->setDefault(0)
                ->setLength(10);
            $table
                ->addColumn(self::COLUMN_INFO_RANK, Type::BIGINT)
                ->setNotnull(false)
                ->setDefault(0)
                ->setLength(10);

            $table
                ->setPrimaryKey([self::COLUMN_ID])
                ->addUniqueIndex([self::COLUMN_DOMAIN_NAME]);

            // create table
            $db->getSchemaManager()->createTable($table);
        }
    }
}
