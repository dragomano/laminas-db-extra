<?php

declare(strict_types=1);

namespace Tests;

use ReflectionClass;
use ReflectionException;

class ReflectionAccessor
{
    private ReflectionClass $reflection;

    private ?object $object;

    /**
     * @throws ReflectionException
     */
    public function __construct(object|string $objectOrClass)
    {
        $this->reflection = new ReflectionClass($objectOrClass);

        if (is_string($objectOrClass)) {
            $this->object = null;
        } else {
            $this->object = $objectOrClass;
        }
    }

    /**
     * @throws ReflectionException
     */
    public function setProperty(mixed $property, mixed $value): void
    {
        $prop = $this->reflection->getProperty($property);
        $prop->setValue($this->object, $value);
    }

    /**
     * @throws ReflectionException
     */
    public function getProperty(string $property): mixed
    {
        $prop = $this->reflection->getProperty($property);

        return $prop->getValue($this->object);
    }

    /**
     * @throws ReflectionException
     */
    public function callMethod(string $method, array $args = []): mixed
    {
        $method = $this->reflection->getMethod($method);

        return $method->invokeArgs($this->object, $args);
    }
}
