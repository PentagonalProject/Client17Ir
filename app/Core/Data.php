<?php
namespace PentagonalProject\Client17Ir\Core;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Types\Type;

/**
 * Class Data
 * @package PentagonalProject\Client17Ir\Core
 */
class Data
{
    const TABLE_NAME = 'ir_data';

    const COLUMN_ID = 'id';
    const COLUMN_DOMAIN_NAME  = 'ir_domain_name';
    const COLUMN_DATE_FREE    = 'date_free';
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
    const COLUMN_REGISTERED_API   = 'api_registered';

    /**
     * @var Db
     */
    protected $database;

    /**
     * Cache constructor.
     */
    public function __construct()
    {
        $this->database = DI::get(Db::class);
        $this->init();
    }

    /**
     * @return Db
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function createQueryBuilder()
    {
        return $this->database->createQueryBuilder();
    }

    /**
     * @param string $domainName
     *
     * @return bool|null
     */
    public function isDomainExists($domainName)
    {
        if (!is_string($domainName)) {
            return null;
        }
        $domainName = trim(strtolower($domainName));
        if ($domainName == '') {
            return null;
        }
        $qb = $this->createQueryBuilder();
        $stmt = $qb
            ->select('1')
            ->from(self::TABLE_NAME)
            ->where(self::COLUMN_DOMAIN_NAME . '= :domainName')
            ->setParameter(':domainName', $domainName)
            ->execute();
        return $stmt->rowCount() > 0;
    }
    /**
     * @param mixed $data
     *
     * @return string
     */
    protected function sanitySet($data)
    {
        return Sanitizer::maybeSerialize($data);
    }

    /**
     * @param string $data
     *
     * @return mixed
     */
    protected function sanityResult($data)
    {
        return Sanitizer::maybeUnSerialize($data);
    }

    /**
     * @param array $data
     *
     * @return \Doctrine\DBAL\Driver\Statement|int|null
     */
    public function set(array $data)
    {
        $datArray = [
            self::COLUMN_DOMAIN_NAME,
            self::COLUMN_DATE_FREE,
            self::COLUMN_DATE_CREATED,
            self::COLUMN_COM,
            self::COLUMN_NET,
            self::COLUMN_ORG,
            self::COLUMN_INFO,
            self::COLUMN_BACK_LINK,
            self::COLUMN_COM_RANK,
            self::COLUMN_NET_RANK,
            self::COLUMN_ORG_RANK,
            self::COLUMN_INFO_RANK,
        ];
        if (!isset($data[self::COLUMN_DOMAIN_NAME])) {
            return null;
        }
        $domain = $data[self::COLUMN_DOMAIN_NAME];
        if (!is_string($domain)) {
            return null;
        }
        $domain = trim(strtolower($domain));
        if ($domain == '') {
            return null;
        }

        unset($data[self::COLUMN_DOMAIN_NAME]);
        $domain = strtolower(trim($domain));
        if ($domain == '') {
            return null;
        }

        foreach($data as $key => $value) {
            if (!in_array($key, $datArray)) {
                unset($data[$key]);
                continue;
            }
        }

        if (empty($data)) {
            return null;
        }

        $exist = $this->isDomainExists($domain);
        if ($exist === null) {
            return null;
        }
        $qb = $this->createQueryBuilder();
        if ($exist) {
            $qb = $qb->update(self::TABLE_NAME);
            if (isset($data[self::COLUMN_DATE_CREATED])) {
                $data[self::COLUMN_DATE_CREATED] = is_string($data[self::COLUMN_DATE_CREATED])
                    ? $data[self::COLUMN_DATE_CREATED]
                    : null;
                if ( $data[self::COLUMN_DATE_CREATED] === null || ! @strtotime($data[self::COLUMN_DATE_CREATED])) {
                    unset($data[self::COLUMN_DATE_CREATED]);
                }
            }

            if (isset($data[self::COLUMN_DATE_FREE])) {
                $data[self::COLUMN_DATE_FREE] = is_string($data[self::COLUMN_DATE_FREE])
                    ? $data[self::COLUMN_DATE_FREE]
                    : null;
                if ( $data[self::COLUMN_DATE_FREE] === null || ! @strtotime($data[self::COLUMN_DATE_FREE])) {
                    unset($data[self::COLUMN_DATE_FREE]);
                }
            }
            foreach ($data as $key => $value) {
                $keyData = ':keyFor_'.md5($key);
                $value = $this->sanitySet($value);
                $qb = $qb->set($key, $keyData)->setParameter($keyData, $value);
            }

            $stmt = $qb
                ->where(self::COLUMN_DOMAIN_NAME . '= :domainName')
                ->setParameter(':domainName', $domain)
                ->execute();
            if ($stmt instanceof Statement) {
                $stmt->closeCursor();
            }
            return $stmt;
        }

        if (!@strtotime($data[self::COLUMN_DATE_CREATED])) {
            $data[self::COLUMN_DATE_CREATED] = date('Y-m-d H:i:s');
        }
        if (!@strtotime($data[self::COLUMN_DATE_FREE])) {
            $data[self::COLUMN_DATE_FREE] = date('Y-m-d H:i:s');
        }
        $data[self::COLUMN_DATE_CREATED] = date('Y-m-d H:i:s', strtotime($data[self::COLUMN_DATE_CREATED]));
        $data[self::COLUMN_DATE_FREE] = date('Y-m-d H:i:s', strtotime($data[self::COLUMN_DATE_FREE]));
        $qb = $qb
            ->insert(self::TABLE_NAME)
            ->setValue(self::COLUMN_DOMAIN_NAME, ':domainName')
            ->setParameter(':domainName', $domain);
        foreach ($data as $key => $value) {
            $keyData = ':keyFor_'.md5($key);
            $qb = $qb->setValue($key, $keyData)->setParameter($keyData, $this->sanitySet($value));
        }

        return $qb->execute();
    }

    /**
     * @param string $domain
     *
     * @return \Doctrine\DBAL\Driver\Statement|int|null
     */
    public function deleteDomain($domain)
    {
        if (!is_string($domain) || trim($domain) == '') {
            return null;
        }

        $domain = trim(strtolower($domain));
        return $this
            ->createQueryBuilder()
            ->delete(self::TABLE_NAME)
            ->where(self::COLUMN_DOMAIN_NAME . '=:domainName')
            ->setParameter(':domainName', $domain)
            ->execute();
    }

    public function setAsRegisteredFromApi($domainName)
    {
        $exist = $this->isDomainExists($domainName);
        if ($exist === null) {
            return null;
        }
        if ($exist) {
            $domainName = trim(strtolower($domainName));
            return $this
                ->createQueryBuilder()
                ->update(self::TABLE_NAME)
                ->set(self::COLUMN_REGISTERED_API, true)
                ->where(self::COLUMN_DOMAIN_NAME . '= :domainName')
                ->setParameter(':domainName', $domainName)
                ->execute();
        }

        return false;
    }

    /**
     * @param string $domainName
     *
     * @return bool|\Doctrine\DBAL\Driver\Statement|int|null
     */
    public function setAsUnRegisteredFromApi($domainName)
    {
        $exist = $this->isDomainExists($domainName);
        if ($exist === null) {
            return null;
        }
        if ($exist) {
            $domainName = trim(strtolower($domainName));
            return $this
                ->createQueryBuilder()
                ->update(self::TABLE_NAME)
                ->set(self::COLUMN_REGISTERED_API, 0)
                ->where(self::COLUMN_DOMAIN_NAME .'= :domainName')
                ->setParameter(':domainName', $domainName)
                ->execute();
        }

        return false;
    }

    /**
     * initial
     */
    protected function init()
    {
        if (!$this->database->tablesExist(self::TABLE_NAME)) {
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
                ->addColumn(self::COLUMN_DATE_FREE, Type::DATETIME)
                ->setNotnull(true)
                ->setDefault('1990-00-00 00:00:00');
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
                ->setDefault(null)
                ->setLength(10);
            $table
                ->addColumn(self::COLUMN_NET_RANK, Type::BIGINT)
                ->setNotnull(false)
                ->setDefault(null)
                ->setLength(10);
            $table
                ->addColumn(self::COLUMN_ORG_RANK, Type::BIGINT)
                ->setNotnull(false)
                ->setDefault(null)
                ->setLength(10);
            $table
                ->addColumn(self::COLUMN_INFO_RANK, Type::BIGINT)
                ->setNotnull(false)
                ->setDefault(null)
                ->setLength(10);

            $table
                ->addColumn(self::COLUMN_REGISTERED_API, Type::BOOLEAN)
                ->setNotnull(true)
                ->setDefault(false);

            $table
                ->setPrimaryKey([self::COLUMN_ID])
                ->addUniqueIndex([self::COLUMN_DOMAIN_NAME]);

            // create table
            $this->database->getSchemaManager()->createTable($table);
        }
    }
}
