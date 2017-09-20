<?php
namespace PentagonalProject\Client17Ir\Core;

/**
 * Class Db
 * @package PentagonalProject\Client17Ir\Core
 * @mixin Database
 */
class Db
{
    /**
     * Db constructor.
     */
    public function __construct()
    {
        DI::set(Database::class, DI::make(Database::class));
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call($name, array $arguments)
    {
        return call_user_func_array([DI::get(Database::class), $name], $arguments);
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public static function __callStatic($name, array $arguments)
    {
        return call_user_func_array([DI::get(Database::class), $name], $arguments);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return Di::get(Database::class)->{$name};
    }
}
