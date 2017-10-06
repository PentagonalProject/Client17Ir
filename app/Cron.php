<?php
namespace PentagonalProject\Client17Ir;

use PentagonalProject\Client17Ir\Core\Config;
use PentagonalProject\Client17Ir\Core\DI;
use PentagonalProject\Client17Ir\Core\TransportIR;

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

