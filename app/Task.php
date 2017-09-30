<?php
namespace PentagonalProject\Client17Ir;

use PentagonalProject\Client17Ir\Core\Config;
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
    $transport->getWebPageDomainList();
} catch (\Exception $e) {
    echo "\n";
    echo "==================== THERE WAS AN ERROR ==================== \n\n";
    echo "[ " . $e->getMessage() . " ]\n\n";
    echo "============================================================ \n\n";
    exit(255);
}