<?php
namespace PentagonalProject\Client17Ir\Worker;

use PentagonalProject\Client17Ir\Core\Cache;
use PentagonalProject\Client17Ir\Core\Data;
use PentagonalProject\Client17Ir\Core\DI;

if (!class_exists('PentagonalProject\Client17Ir\Core\DI')) {
    return;
}

DI::get(Data::class);
DI::get(Cache::class);
