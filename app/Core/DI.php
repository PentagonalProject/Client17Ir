<?php
namespace PentagonalProject\Client17Ir\Core;

use DI\Container;
use DI\ContainerBuilder;
use DI\Definition\Helper\DefinitionHelper;

/**
 * Class DI
 * @package PentagonalProject\Client17Ir\Core
 * @mixin Container
 * @method static mixed make($name, array $parameters = [])
 * @method static bool has($name)
 * @method static mixed call($callable, array $parameters = [])
 * @method static mixed|DefinitionHelper set($name, $value)
 * @method static mixed get($name)
 * @method static object injectOn($instance)
 */
final class DI
{
    /**
     * @var Container
     */
    private static $container;

    /**
     * DI constructor.
     */
    private function __construct()
    {
        @date_default_timezone_set('UTC');
    }

    public static function once(array $config)
    {
        if (!isset(self::$container)) {
            $object = new self();
            $config    =  new Config($config);
            /**
             * @var Config $dbConfig
             */
            $dbConfig   = $config['db'];
            $container  = new ContainerBuilder();
            $reflection = new \ReflectionClass(Database::class);
            $parameters = $reflection->getConstructor()->getParameters();
            if (!empty($parameters)) {
                $container->addDefinitions([
                    Database::class => \DI\object(Database::class)
                        ->constructorParameter($parameters[0]->getName(), $dbConfig->toArray())
                ]);
            }
            self::$container = $container->build();
            self::$container->set(DI::class, $object);
            self::$container->set(Config::class, $config);
            return $object;
        }

        return self::$container->get(DI::class);
    }

    /**
     * @return Container
     */
    public static function getDIContainer()
    {
        if (! self::$container) {
            $object = new self();
            return $object::$container;
        }

        return self::$container;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call($name, array $arguments)
    {
        return call_user_func_array([self::getDIContainer(), $name], $arguments);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @mixin Container
     * @return mixed
     */
    public static function __callStatic($name, array $arguments)
    {
        return call_user_func_array([self::getDIContainer(), $name], $arguments);
    }
}
