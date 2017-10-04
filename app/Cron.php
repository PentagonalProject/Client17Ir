<?php
namespace PentagonalProject\Client17Ir;

use PentagonalProject\Client17Ir\Core\Config;
use PentagonalProject\Client17Ir\Core\Data;
use PentagonalProject\Client17Ir\Core\DI;
use PentagonalProject\Client17Ir\Core\TransportIR;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ .'/Functions.php';
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
$transport->cliVerbose = true;
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
    if ($exist) {
        $transport->addVerbose("Updating Domain : {$domain}");
        $qb
            ->update(Data::TABLE_NAME)
            ->set(Data::COLUMN_DATE_CREATED, ':dateCreated')
            ->setParameter(':dateCreated', $date)
            ->execute();
    } else {
        $transport->addVerbose("Saving Domain : {$domain}");
        $qb
            ->insert(Data::TABLE_NAME)
            ->values([
                Data::COLUMN_DOMAIN_NAME => ':domainName',
                Data::COLUMN_DATE_CREATED => ':dateCreated',
            ])->setParameters([
                ':domainName' => $domain,
                ':dateCreated' => $date
            ])->execute();
    }

    $domainToCheck[$domain] = true;
}

$transport->addVerbose("\nValid domain total is : ".count($domainToCheck));

/**
 * Checking Whois
 */
$transport->addVerbose("==================== Getting Whois Data ====================");

$whois = who();
$verifier = $whois->getVerifier();
$extensionToCheck = ['com', 'net', 'org', 'info'];
foreach ($domainToCheck as $domainName => $status) {
    $domainArray  = $whois->getVerifier()->validateDomain($domainName);
    unset($domainToCheck[$domainName]);
    if (!empty($domainArray[$verifier::SELECTOR_DOMAIN_NAME])) {
        $domainToCheck[$domainName] = [];
        $baseDomain = $domainArray[$verifier::SELECTOR_DOMAIN_NAME];
        foreach ($extensionToCheck as $extension) {
            $domain = "{$baseDomain}.{$extension}";
            $transport->addVerbose("Checking: {$domain}", false);
            $isRegistered = isDomainRegistered($domain);
            $domainToCheck[$domainName][$extension] = $isRegistered;
            $transport->addVerbose($isRegistered ? " -> is Registered" : " -> is not Registered");
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
