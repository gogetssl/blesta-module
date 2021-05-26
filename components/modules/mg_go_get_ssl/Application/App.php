<?php

namespace MgGoGetSsl\Application;

use ReflectionType;
use ReflectionClass;

final class App
{

    /** @var array */
    private static $instances;

    /** @var Application */
    private static $application;

    private function __clone() {}
    private function __construct() {}
    private function __wakeup() {}

    /**
     * @param Application $application
     */
    public static function setApplication(Application $application)
    {
        self::$application = $application;
    }

    /**
     * Create instance of given class with automatic dependency injection
     *
     * @throws \Exception when requested class does not exist
     * @param string $class
     * @param bool   $force
     * @return object|void
     */
    public static function make($class, $force = false)
    {
        if (isset(self::$instances[$class]) && !$force) {
            return self::$instances[$class];
        }

        if (!class_exists($class) && !interface_exists($class)) {
            throw new \Exception(sprintf('Class %s does not exist', $class));
        }

        $parameters = [];
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                $parameterClass = $parameter->getClass();
                
                if ($parameterClass instanceof ReflectionClass) {
                    if ($parameterClass->getName() == Application::class) {
                        $parameters[] = self::$application;
                        continue;
                    }

                    $parameters[] = self::make($parameterClass->getName());
                } else {
                    if ($parameter->isDefaultValueAvailable()) {
                        $parameters[] = $parameter->getDefaultValue();
                        continue;
                    }

                    $parameterType = $parameter->getType();

                    if ($parameterType instanceof ReflectionType) {
                        $parameters[] = self::generateParameterValue($parameterType->__toString());
                        continue;
                    }

                    $parameters[] = null;
                }
            }
        }

        if (!interface_exists($reflection->name)) {
            $object = $reflection->newInstanceArgs($parameters);
            self::$instances[$class] = $object;

            return $object;
        }
    }

    /**
     * Generate default parameter by type
     *
     * @param string $type
     * @return mixed
     */
    private static function generateParameterValue($type)
    {
        switch ($type) {
            case 'int':
                return 1;
            case 'float':
                return 1.0;
            case 'array':
                return [];
            case 'string':
                return '';
            case 'bool':
                return true;
            default:
                return null;
        }
    }

}
