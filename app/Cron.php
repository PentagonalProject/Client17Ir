<?php
namespace PentagonalProject\Client17Ir;

use GuzzleHttp\Client;
use PentagonalProject\Client17Ir\Core\Config;
use PentagonalProject\Client17Ir\Core\Data;
use PentagonalProject\Client17Ir\Core\DI;
use PentagonalProject\Client17Ir\Core\TransportIR;
use Psr\Http\Message\ResponseInterface;
use Wa72\HtmlPageDom\HtmlPageCrawler;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ .'/Functions.php';

// set to UTC
date_default_timezone_set('UTC');

try {
    DI::once(require __DIR__ . '/../config.php');
} catch (\Exception $e) {
    echo "\n";
    echo "==================== THERE WAS AN ERROR ==================== \n\n";
    echo "[ " . $e->getMessage() . " ]\n\n";
    echo "============================================================ \n\n";
    exit(255);
}

/**
 * @var Config $config
 */
$config = DI::get(Config::class);
$apiKey = $config->get('api_key');

if (!is_string($apiKey) || trim($apiKey) === '') {
    echo "\n";
    echo "==================== THERE WAS AN ERROR ==================== \n\n";
    echo "                 [ API KEY CAN NOT EMPTY! ]\n\n";
    echo "============================================================ \n\n";
    exit(255);
}

$transport = new TransportIR($apiKey);
$transport->cliVerbose = (bool) $config->get('debug');

try {
    $domainList = $transport->getWebPageDomainList();
} catch (\Exception $e) {
    echo "\n";
    echo "==================== THERE WAS AN ERROR ==================== \n\n";
    echo "[ " . $e->getMessage() . " ]\n\n";
    echo "============================================================ \n\n";
    exit(255);
}

/**
 * @var Data $dataStore
 */
$dataStore = DI::get(Data::class);
// doing verbose
$transport->addVerbose('Add domain data into database');

/**
 * Store Data Into Database
 */
$domainToCheck = [];
foreach($domainList as $domain => $date) {
    $domain = trim(strtolower($domain));
    if ($domain == '') {
        continue;
    }
    $exist = $dataStore->isDomainExists($domain);
    if ($exist === null) {
        continue;
    }
    $qb = $dataStore->getDatabase()->createQueryBuilder();
    $currentDate = gmdate('Y-m-d H:i:s');
    if ($exist) {
        $transport->addVerbose("Updating Domain : {$domain}");
        $qb
            ->update(Data::TABLE_NAME)
            ->set(Data::COLUMN_DATE_FREE, ':dateCreated')
            ->set(Data::COLUMN_DATE_CREATED, ':dateCron')
            ->setParameter(':dateCreated', $date)
            ->setParameter(':dateCron', $currentDate)
            ->execute();
    } else {
        $transport->addVerbose("Saving Domain : {$domain}");
        $qb
            ->insert(Data::TABLE_NAME)
            ->values([
                Data::COLUMN_DOMAIN_NAME  => ':domainName',
                Data::COLUMN_DATE_FREE    => ':dateCreated',
                Data::COLUMN_DATE_CREATED => ':dateCron',
            ])->setParameters([
                ':domainName' => $domain,
                ':dateCreated' => $date,
                ':dateCron' => $currentDate,
            ])->execute();
    }

    $domainToCheck[$domain] = true;
}

$transport->addVerbose("\nValid domain total is : ".count($domainToCheck));

// ---------------------------------------------------------------------------- \\
// END GRAB
// ---------------------------------------------------------------------------- \\

/**
 * Checking Whois
 */
$transport->addVerbose("==================== Getting Whois Data ====================");

$whois = who();
// set cache to 24H
$whois->setCacheTimeOut(3600*24);
$verifier = $whois->getVerifier();
$extensionToCheck = ['com', 'net', 'org', 'info'];
$d = $dataStore
    ->getDatabase()
    ->createQueryBuilder()
    ->select(Data::COLUMN_DOMAIN_NAME)
    ->from(Data::TABLE_NAME)
    ->execute();
$domainToCheck = [];
while ($row = $d->fetch(\PDO::FETCH_ASSOC)) {
    $domainToCheck[$row[Data::COLUMN_DOMAIN_NAME]] = true;
}

foreach ($domainToCheck as $domainName => $status) {

    $domainArray  = $whois->getVerifier()->validateDomain($domainName);
    unset($domainToCheck[$domainName]);
    if (!empty($domainArray) && !empty($domainArray[$verifier::SELECTOR_DOMAIN_NAME])) {
        $domainToCheck[$domainName] = [];
        $baseDomain = $domainArray[$verifier::SELECTOR_DOMAIN_NAME];
        foreach ($extensionToCheck as $extension) {
            $domain = "{$baseDomain}.{$extension}";
            $transport->addVerbose("Checking: {$domain}", false);
            $domainIsRegistered = isDomainRegistered($domain);
            $isRegistered = $domainIsRegistered === true;
            $domainToCheck[$domainName][$extension] = $isRegistered;
            $transport->addVerbose($isRegistered ? " -> is Registered" : " -> is not Registered");
            // if null response it means that no result
            if ($domainIsRegistered === null) {
                // no update
                continue;
            }
            /**
             * update database
             */
            $qb = $dataStore->getDatabase()->createQueryBuilder();
            $isExtension = "is_{$extension}";
            try {
                $qb
                    ->update(Data::TABLE_NAME)
                    ->set($isExtension, $isRegistered ? 'true' : 'false')
                    ->where(Data::COLUMN_DOMAIN_NAME . '= :domainName')
                    ->setParameter(':domainName', $domainName)
                    ->execute();
            } catch (\Exception $e) {
                $transport->addVerbose("Error: {$e->getMessage()}");
            }
        }
    }
}

// ---------------------------------------------------------------------------- \\
// END DOMAIN CHECK
// ---------------------------------------------------------------------------- \\

/**
 * Checking Whois
 */
$transport->addVerbose("==================== Getting ALEXA RANK ====================");

$client = new Client([
    'headers' => [
        'User-Agent' => $transport::UA
    ]
]);

foreach ($domainToCheck as $domainName => $extensionList) {
    $domainArray  = $whois->getVerifier()->validateDomain($domainName);
    $baseDomain = $domainArray[$verifier::SELECTOR_DOMAIN_NAME];
    foreach ($extensionList as $extension => $registered) {
        $domain = "{$baseDomain}.{$extension}";
        $transport->addVerbose("Checking Alexa Rank: {$domain}");
        $uri = 'https://www.alexa.com/siteinfo/' . $domain;
        $cacheKey = sha1($uri);
        $data = cacheGet($cacheKey);
        if (!is_string($data) || trim($data) == '') {
            try {
                $response = $client->get($uri);
            } catch (\Exception $e) {
                if (preg_match('/timed?\s+out/i', $e->getMessage())) {
                    $this->addVerbose("Alexa Rank check time out. Retrying...");
                    try {
                        $response = $client->get($uri);
                    } catch (\Exception $e) {
                        // pass
                    }
                }
            }
            if ( ! isset($response) || ! $response instanceof ResponseInterface) {
                $transport->addVerbose("Skipped : {$domain}");
                continue;
            }
            $data = '';
            $body = $response->getBody();
            while ( ! $body->eof()) {
                $data .= $body->getContents();
            }
            // add cache
            cachePut($cacheKey, $data, 3600*24);
        }

        $data   = HtmlPageCrawler::create($data);
        $metric = $data->filter('.metrics-data');
        if (!$metric->count()) {
            unset($data, $metric);
            continue;
        }

        $rank = trim($metric->first()->text());
        $rank = $rank == '-' ? null : abs(preg_replace('/[^0-9]/', '', $rank));
        $rank = $rank ? $rank : ($rank === null ? null : 0);
        $transport->addVerbose("Alexa Rank For: {$domain} is -> {$rank}");
        $qb = $dataStore->getDatabase()->createQueryBuilder();
        try {
            $qb
                ->update(Data::TABLE_NAME)
                ->set("{$extension}_rank", ($rank === null ? 'null' : $rank))
                ->where(Data::COLUMN_DOMAIN_NAME . '= :domainName')
                ->setParameter(':domainName', $domainName)
                ->execute();
        } catch (\Exception $e) {
            $transport->addVerbose("Error: {$e->getMessage()}");
        }
    }
}

// ---------------------------------------------------------------------------- \\
// END ALEXA RANK
// ---------------------------------------------------------------------------- \\
