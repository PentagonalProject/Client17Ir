<?php
namespace PentagonalProject\Client17Ir;

use PentagonalProject\Client17Ir\Core\Database;
use PentagonalProject\Client17Ir\Core\DI;

require __DIR__ . '/../vendor/autoload.php';

DI::once(require __DIR__ . '/../config.php');
require_once __DIR__ .'/Worker/InitWorker.php';
require_once __DIR__ .'/Worker/GetWhoIsData.php';
