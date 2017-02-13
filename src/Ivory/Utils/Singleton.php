<?php
namespace Ivory\Utils;

/**
 * Singleton functionality for any classes, i.e., the exhibiting class is limited to have only one instance globally.
 */
trait Singleton
{
    final private function __construct()
    {
        static::initializeSingleton();
    }

    /**
     * Initialization method of the singleton - gets called upon the singleton instance construction.
     *
     * To be overridden by classes which need to initialize.
     */
    protected function initializeSingleton() { }

    /**
     * Creates the singleton instance, if it has not been created yet, and returns it.
     *
     * After creating the instance, {@link initializeSingleton()} is called to perform custom initialization.
     *
     * @return static the singleton instance
     */
    final public static function getInstance()
    {
        static $instances = [];
        $class = get_called_class();
        if (!isset($instances[$class])) {
            $instances[$class] = new static();
        }
        return $instances[$class];
    }

    final private function __clone() { }

    final private function __wakeup() { }
}
