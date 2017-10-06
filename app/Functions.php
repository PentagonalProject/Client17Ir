<?php
namespace PentagonalProject\Client17Ir;

use Pentagonal\WhoIs\Util\DataGetter;
use Pentagonal\WhoIs\Verifier;
use Pentagonal\WhoIs\WhoIs;
use PentagonalProject\Client17Ir\Core\Cache;
use PentagonalProject\Client17Ir\Core\Data;
use PentagonalProject\Client17Ir\Core\Db;
use PentagonalProject\Client17Ir\Core\DI;
use PentagonalProject\Client17Ir\Core\TransportIR;

/**
 * The Whois Server Instance
 * to use it just call @uses who()
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
 * Function to check if domain registered ot not
 *
 * @param string $domainName
 * @return bool|null returning null if there was error or invalid response maybe limit or empty / failed result
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
 * This is for cache
 *
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
 * Saving cache
 *
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
 * Getting Cache data
 *
 * @param string $key
 *
 * @return mixed|null
 */
function cacheGet($key)
{
    return cache()->get($key);
}

/**
 * Checking cache if exists
 *
 * @param string $key
 *
 * @return bool
 */
function cacheExist($key)
{
    return cache()->exist($key);
}

/**
 * Delete cache data
 *
 * @param string $key
 *
 * @return bool|int
 */
function cacheDelete($key)
{
    return cache()->delete($key);
}

/**
 * This is database object
 *
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

/**
 * This miscellaneous function to get list of IR Domain
 *
 * @param TransportIR $transport
 *
 * @return array
 */
function getDomainIRListToCheck(TransportIR $transport)
{
    $domainList = $transport->getWebPageDomainList();
    /**
     * @var Data $dataStore
     */
    $dataStore = DI::get(Data::class);
    /**
     * Store Data Into Database
     */
    $domainToCheck = [];
    $transport->addVerbose('Add domain data into database');
    foreach ($domainList as $domain => $date) {
        $domain = trim(strtolower($domain));
        if ($domain == '') {
            continue;
        }
        $transport->addVerbose("Saving Domain : [ {$domain} ]");
        $currentDate = gmdate('Y-m-d H:i:s');
        $res = $dataStore->set(
            [
                Data::COLUMN_DOMAIN_NAME => $domain,
                Data::COLUMN_DATE_FREE => $date,
                Data::COLUMN_DATE_CREATED => $currentDate
            ]
        );
        if ($res === null) {
            continue;
        }
        $domainToCheck[$domain] = true;
    }

    $transport->addVerbose("\nValid domain total is : ".count($domainToCheck));
    return $domainToCheck;
}

/**
 * This miscellaneous function to get Whois result that saving into database
 * that domain is registered or not the values of domain check @uses getDomainIRListToCheck()
 *
 * @param array $domainToCheck
 * @param TransportIR $transport
 *
 * @return array
 */
function getDomainWhoIsFromArrayIR(array $domainToCheck, TransportIR $transport)
{
    // set cache to 24H
    who()->setCacheTimeOut(3600*24);
    /**
     * @var Data $dataStore
     */
    $dataStore = DI::get(Data::class);
    $extensionToCheck = ['com', 'net', 'org', 'info'];
    foreach ($domainToCheck as $domainName => $status) {
        $domainArray  = who()->getVerifier()->validateDomain($domainName);
        unset($domainToCheck[$domainName]);
        if (!empty($domainArray) && !empty($domainArray[Verifier::SELECTOR_DOMAIN_NAME])) {
            $domainToCheck[$domainName] = [];
            $baseDomain = $domainArray[Verifier::SELECTOR_DOMAIN_NAME];
            foreach ($extensionToCheck as $extension) {
                $domain = "{$baseDomain}.{$extension}";
                $transport->addVerbose("Checking [ {$domain} ] ", false);
                $domainIsRegistered = isDomainRegistered($domain);
                $isRegistered = $domainIsRegistered === true;
                $domainToCheck[$domainName][$extension] = $isRegistered;
                $transport->addVerbose($isRegistered ? " -> is Registered" : " -> is not Registered");
                // if null response it means that no result
                if ($domainIsRegistered === null) {
                    continue;
                }
                /**
                 * update database
                 */
                $isExtension = "is_{$extension}";
                $dataStore->set([
                    Data::COLUMN_DOMAIN_NAME => $domainName,
                    $isExtension => ($isRegistered ? true : false)
                ]);
            }
        }
    }

    return $domainToCheck;
}

/**
 * This is get alexa RANK for certain domain,
 * The values of $domainToCheck is from @uses getDomainWhoIsFromArrayIR()
 *
 * @param array $domainToCheck
 * @param TransportIR $transport
 * @param bool $safe alexa detect unwanted traffic
 *
 * @return array
 */
function getAlexaRankFromArrayIR(array $domainToCheck, TransportIR $transport, $safe = true)
{
    $dataStore = DI::get(Data::class);
    $isAlexaStopped = false;
    foreach ($domainToCheck as $domainName => $extensionList) {
        if ($isAlexaStopped) {
            break;
        }
        if (!is_array($extensionList)) {
            continue;
        }

        $domainArray = who()->getVerifier()->validateDomain($domainName);
        $baseDomain  = $domainArray[Verifier::SELECTOR_DOMAIN_NAME];
        foreach ($extensionList as $extension => $registered) {
            if (!is_string($extension)) {
                continue;
            }

            $domain = "{$baseDomain}.{$extension}";
            $rank   = $transport->getAlexa($domain);
            if ($rank === false) {
                continue;
            }
            if ($rank === -1) {
                $isAlexaStopped = true;
                $transport->addVerbose("");
                $transport->addVerbose(
                    "==================== ALEXA BLOCKING YOUR IP. ALEXA CHECK STOPPED HERE! ===================="
                );
                $transport->addVerbose("");
                break;
            }
            $isCache = isset($rank['cache']) && $rank['cache'];
            $rank = isset($rank['result']) ? $rank['result'] : null;
            $rankDisplay = $rank === null ? 'Unknown' : $rank;
            $transport->addVerbose("Alexa Rank For: [ {$domain} ] is -> {$rankDisplay}");
            $dataStore->set([
                Data::COLUMN_DOMAIN_NAME => $domainName,
                "{$extension}_rank"      => ($rank === null ? 'null' : $rank)
            ]);

            // use safe
            if ($safe && ! $isCache) {
                $transport->addVerbose("Waiting 3 second for next Request ....");
                sleep(3);
            }
        }
    }

    return $domainToCheck;
}

/**
 * Get google backlink from list domains of array
 * example structure for value $domainList is
 *
 * array(
 *   'domain.com',
 *   'example.com',
 *   'otherdomain.com'
 *   ..... etc
 * )
 *
 * @param array $domainList
 * @param TransportIR $transport
 * @param bool $safe
 *
 * @return array result will be return that domains succeed from checking backlink as array result
 */
function getGoogleBackLink(array $domainList, TransportIR $transport, $safe = true)
{
    /**
     * @var Data $dataStore
     */
    $dataStore = DI::get(Data::class);
    $result = [];
    foreach ($domainList as $domainName) {
        if (!is_string($domainName)) {
            continue;
        }
        if (!$dataStore->isDomainExists($domainName)) {
            continue;
        }
        try {
            $domainName = trim(strtolower($domainName));
            $transport->addVerbose("Getting Google Backlink for : {$domainName}");
            $backLink = $transport->getGoogleBackLink($domainName);
            $result[$domainName] = $backLink;
        } catch (\Exception $e) {
            $transport->addVerbose("Backlink {$domainName} has error: {$e->getMessage()}");
            continue;
        }

        if ($backLink === -1) {
            $transport->addVerbose("");
            $transport->addVerbose(
                "==================== GOOGLE BLOCKING YOUR IP. BACKLINK CHECK STOPPED HERE! ===================="
            );
            $transport->addVerbose("");
            break;
        }
        if (is_array($backLink) && isset($backLink['result'])) {
            if (empty($backLink['result'])) {
                $transport->addVerbose("Backlink [ {$domainName} ] Not found");
            } else {
                $transport->addVerbose("Backlink [ {$domainName} ] found -> " . count($backLink['result']));
                $dataStore->set([
                    Data::COLUMN_BACK_LINK => $backLink['result'],
                    Data::COLUMN_DOMAIN_NAME => $domainName
                ]);
            }
        }

        // safe for 3 second
        if ($safe && (!isset($backLink['cache']) || isset($backLink['cache']) && ! $backLink['cache'])) {
            $transport->addVerbose("Waiting 3 second for next Request ....");
            sleep(3);
        }
    }

    return $result;
}

/**
 * Get domain data from database
 *
 * @param string|int $alexaRank use null to get data with no check alexa rank,
 *                              otherwise fill with numeric value / integer eg with 1 million is
 *                              1000000 < integer 1 Million
 * @param string $date          Date that get domain of stored on database
 *                              eg to get data of today use php script
 *                              date('Y-m-d');
 *
 * @param string|array $extensionAlexa  list of alexa rank to be check that use $alexaRank
 *                                      valid extension only com,net,info,org
 *                                      use ['com', 'net', 'org', 'info'] to check all rank mustbe
 *                                      same or below on parameter $alexaRank
 *
 * @return array  key as string domain value as boolean is has been
 *      registered or not (this stored on database with colum : api_registered)
 *      Please create custom function to save data on database as automation
 */
function getDomainListIRAllRegisteredFromDatabase($alexaRank, $date = null, $extensionAlexa = 'com')
{
    if (is_null($date) || is_bool($date)) {
        $date = date('Y-m-d');
    }
    if ($alexaRank !== null) {
        if (! is_string($date) || ! is_numeric($alexaRank)) {
            return [];
        }
        $alexaRank = (int)abs($alexaRank);
        if ($alexaRank < 0) {
            return [];
        }
    }

    $date = @strtotime($date);
    if (!$date) {
        return [];
    }

    $date = date('Y-m-d', $date);
    /**
     * @var Data $dataStore
     */
    $dataStore = DI::get(Data::class);
    $qb = $dataStore->createQueryBuilder();
    $qb = $qb
        ->select('*')
        ->from(Data::TABLE_NAME)
        ->andwhere(Data::COLUMN_DATE_CREATED . '= :dateCreated')
        ->setParameter(':dateCreated', $date)
        ->andwhere(Data::COLUMN_ORG  . '= 1')
        ->andwhere(Data::COLUMN_COM  . '= 1')
        ->andwhere(Data::COLUMN_INFO . '= 1')
        ->andwhere(Data::COLUMN_NET  . '= 1');
    if ($alexaRank !== null) {
        if (is_string($extensionAlexa)) {
            $extensionAlexa = [$extensionAlexa];
        }
        if (! is_array($extensionAlexa)) {
            $extensionAlexa = null;
        } else {
            $tmpExtension = $extensionAlexa;
            foreach ($tmpExtension as $ext) {
                if (! is_string($ext)) {
                    continue;
                }
                $ext = trim(strtolower($ext));
                if (in_array($ext, ['net', 'com', 'org', 'info'])) {
                    continue;
                }
                $newExt = "{$ext}_rank";
                $qb     = $qb
                    ->andWhere("{$newExt} NOT null")
                    ->andWhere("{$newExt} <= :alexaRank{$ext}")
                    ->setParameter(":alexaRank{$ext}", $alexaRank);
            }
        }
    }

    try {
        $data = [];
        $stmt = $qb->execute();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $domain = strtolower((string) $row[Data::COLUMN_DOMAIN_NAME]);
            $data[$domain] = $row[Data::COLUMN_REGISTERED_API] ? true : false;
        }
        return $data;
    } catch (\Exception $e) {
        return [];
    }
}
