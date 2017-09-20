<?php
namespace PentagonalProject\Client17Ir\Core;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Types\Type;
use Pentagonal\WhoIs\Interfaces\CacheInterface;

/**
 * Class Cache
 * @package PentagonalProject\Client17Ir\Core
 */
class Cache implements CacheInterface
{
    const TABLE_NAME = 'ir_cache_data';

    const COLUMN_IDENTIFIER   = 'identifier';
    const COLUMN_CONTENT      = 'cache_content';
    const COLUMN_CREATED_DATE = 'created_date';
    const COLUMN_EXPIRED_TIME = 'expired_time';

    /**
     * Cache constructor.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    protected function createQueryBuilder()
    {
        /**
         * @var Db $db
         */
        $db = DI::get(Db::class);
        return $db->createQueryBuilder();
    }

    /**
     * @param mixed $data
     *
     * @return string
     */
    protected function sanitySet($data)
    {
        return $data;
    }

    /**
     * @param string $data
     *
     * @return mixed
     */
    protected function sanityResult($data)
    {
        return $data;
    }

    /**
     * Initial
     */
    protected function init()
    {
        $db = DI::get(Db::class);

        if (!$db->tablesExist(self::TABLE_NAME)) {
            $table = new Table(self::TABLE_NAME);
            $table
                ->addColumn(self::COLUMN_IDENTIFIER, Type::STRING)
                ->setLength(255)
                ->setNotnull(true);
            $table
                ->addColumn(self::COLUMN_CONTENT, Type::TEXT)
                ->setLength(16777216)
                ->setNotnull(false)
                ->setDefault(null);
            $table
                ->addColumn(self::COLUMN_CREATED_DATE, Type::DATETIME)
                ->setNotnull(true);
            $table
                ->addColumn(self::COLUMN_EXPIRED_TIME, Type::BIGINT)
                ->setNotnull(true);
            $db->getSchemaManager()->createTable($table);
        }
    }

    /**
     * @param string $identifier
     *
     * @return bool
     */
    public function exist($identifier)
    {
        if (!is_string($identifier)) {
            return false;
        }

        return (bool) $this
            ->createQueryBuilder()
            ->select('1')
            ->from(self::TABLE_NAME)
            ->where(self::COLUMN_IDENTIFIER .'=:name')
            ->setParameter(':name', $identifier)
            ->execute()
            ->rowCount();
    }

    /**
     * @param int|string $identifier
     *
     * @return bool|int
     */
    public function delete($identifier)
    {
        if (!is_string($identifier)) {
            return false;
        }

        return $this
            ->createQueryBuilder()
            ->delete(self::TABLE_NAME)
            ->where(self::COLUMN_IDENTIFIER . '=:name')
            ->setParameter(':name', $identifier)
            ->execute();
    }

    /**
     * @param int|string $identifier
     * @param mixed $data
     * @param int $timeout
     *
     * @return \Doctrine\DBAL\Driver\Statement|int
     */
    public function put($identifier, $data, $timeout = 3600)
    {
        if (!is_string($identifier)) {
            return 0;
        }

        if (!$this->exist($identifier)) {
            return $this
                ->createQueryBuilder()
                ->insert(self::TABLE_NAME)
                ->values([
                    self::COLUMN_IDENTIFIER   => ':name',
                    self::COLUMN_CONTENT      => ':content',
                    self::COLUMN_CREATED_DATE => ':created',
                    self::COLUMN_EXPIRED_TIME => ':expired'
                ])->setParameters([
                    ':name'    => $identifier,
                    ':content' => $this->sanitySet($data),
                    ':created' => date('Y-m-d H:i:s'),
                    ':expired' => date('Y-m-d H:i:s', time() + $timeout),
                ])->execute();
        }

        return $this
            ->createQueryBuilder()
            ->update(self::TABLE_NAME)
            ->set(self::COLUMN_CONTENT, ':content')
            ->set(self::COLUMN_CREATED_DATE, ':created')
            ->set(self::COLUMN_EXPIRED_TIME, ':expired')
            ->where($identifier . '=:identity')
            ->setParameters([
                ':content' => $this->sanitySet($data),
                ':created' => date('Y-m-d H:i:s'),
                ':expired' => date('Y-m-d H:i:s', time() + $timeout),
                ':identity' => $identifier
            ])->execute();
    }

    /**
     * @param string $identifier
     *
     * @return mixed|null
     */
    public function get($identifier)
    {
        if (!is_string($identifier)) {
            return null;
        }

        $stmt = $this
            ->createQueryBuilder()
            ->select(self::COLUMN_CONTENT, self::COLUMN_EXPIRED_TIME, self::COLUMN_CREATED_DATE)
            ->from(self::TABLE_NAME)
            ->where(self::COLUMN_IDENTIFIER .'=:name')
            ->setParameter(':name', $identifier)
            ->setMaxResults(1)
            ->execute();

        $fetch = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($stmt instanceof Statement) {
            $stmt->closeCursor();
        }

        if (is_array($fetch) && isset($fetch[self::COLUMN_CONTENT])) {
            $expired = @strtotime($fetch[self::COLUMN_EXPIRED_TIME]);
            if (!$expired || $expired < time()) {
                $this->delete($identifier);
                return null;
            }
            return $this->sanityResult($fetch[self::COLUMN_CONTENT]);
        }

        return null;
    }
}
