<?php
/**
 * This file is based on file cron task worker
 * This is use procedural step
 *
 * 1. validate config
 * 2. Getting domain .ir list
 * 3. Checking Domain Availibility
 * 4. Getting Domain ALEXA Rank
 * 5. Getting Backlink From Google
 */
namespace PentagonalProject\Client17Ir;

use PentagonalProject\Client17Ir\Core\Config;
use PentagonalProject\Client17Ir\Core\DI;
use PentagonalProject\Client17Ir\Core\TransportIR;

# include these files
require __DIR__ . '/../vendor/autoload.php'; # vendor autoload
require_once __DIR__ .'/Functions.php';      # this scripts functions
# The developer will be known about how to use this functions & scripts
#

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
//var_dump(getDomainListIRAllRegisteredFromDatabase(null));
//exit;

$transport = new TransportIR($apiKey);
$transport->cliVerbose = (bool) $config->get('debug');

// ---------------------------------------------------------------------------- \\
// START GRAB
// ---------------------------------------------------------------------------- \\
try {
    $domainToCheck = getDomainIRListToCheck($transport);
} catch (\Exception $e) {
    echo "\n";
    echo "==================== THERE WAS AN ERROR ==================== \n\n";
    echo "[ " . $e->getMessage() . " ]\n\n";
    echo "============================================================ \n\n";
    exit(255);
}
// ---------------------------------------------------------------------------- \\
// END GRAB
// ---------------------------------------------------------------------------- \\

// ---------------------------------------------------------------------------- \\
// START DOMAIN CHECK
// ---------------------------------------------------------------------------- \\
/**
 * Checking Whois
 */
$transport->addVerbose("==================== Getting Whois Data ====================");
$domainToCheck = getDomainWhoIsFromArrayIR($domainToCheck, $transport);
// ---------------------------------------------------------------------------- \\
// END DOMAIN CHECK
// ---------------------------------------------------------------------------- \\

// ---------------------------------------------------------------------------- \\
// START ALEXA RANK
// ---------------------------------------------------------------------------- \\
/**
 * Checking ALEXA
 */
$transport->addVerbose("==================== Getting ALEXA RANK ====================");
$domainToCheck = getAlexaRankFromArrayIR($domainToCheck, $transport);
// ---------------------------------------------------------------------------- \\
// END ALEXA RANK
// ---------------------------------------------------------------------------- \\

// ---------------------------------------------------------------------------- \\
// START GOOGLE BACKLINK
// ---------------------------------------------------------------------------- \\
/**
 * Checking Back Link
 */
$transport->addVerbose("==================== Getting GOOGLE BACKLINK ====================");
getGoogleBackLink(array_keys($domainToCheck), $transport);

// ---------------------------------------------------------------------------- \\
// END GOOGLE BACKLINK
// ---------------------------------------------------------------------------- \\

