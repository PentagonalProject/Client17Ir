<?php
namespace PentagonalProject\Client17Ir;

use Pentagonal\WhoIs\Util\DataGetter;
use Pentagonal\WhoIs\WhoIs;
use PentagonalProject\Client17Ir\Core\Cache;
use PentagonalProject\Client17Ir\Core\Data;
use PentagonalProject\Client17Ir\Core\Db;
use PentagonalProject\Client17Ir\Core\DI;

/**
 * @return WhoIs
 */
function &who()
{
    static $whoIs;
    if (!isset($whoIs)) {
        $whoIs = new WhoIs(new DataGetter(), cache());
    }

    return $whoIs;
}

/**
 * @param string $domainName
 * @return bool|null
 */
function isDomainRegistered($domainName)
{
    try {
        return who()->isDomainRegistered($domainName);
    } catch (\Exception $e) {
        return null;
    }
}

/**
 * @return Cache
 */
function cache()
{
    /**
     * @var Cache $cache
     */
    $cache = DI::get(Cache::class);
    return $cache;
}

/**
 * @param string $key
 * @param mixed $value
 * @param int $expire
 *
 * @return \Doctrine\DBAL\Driver\Statement|int
 */
function cachePut($key, $value, $expire = 3600)
{
    return cache()->put($key, $value, $expire);
}

/**
 * @param string $key
 *
 * @return mixed|null
 */
function cacheGet($key)
{
    return cache()->get($key);
}

/**
 * @param string $key
 *
 * @return bool
 */
function cacheExist($key)
{
    return cache()->exist($key);
}

/**
 * @param string $key
 *
 * @return bool|int
 */
function cacheDelete($key)
{
    return cache()->delete($key);
}

/**
 * @return Db
 */
function &db()
{
    static $db;
    if (!isset($db)) {
        /**
         * @var Data $data
         */
        $data = DI::get(Data::class);
        $db = $data->getDatabase();
    }

    return $db;
}
