<?php

namespace App\Core;

use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

class Container
{
    private array $definitions = [];
    private array $instances = [];

    public function set(string $id, callable $factory, bool $shared = true): void
    {
        $this->definitions[$id] = [
            'factory' => $factory,
            'shared' => $shared,
        ];
    }

    public function autowire(string $id, ?string $className = null, bool $shared = true): void
    {
        $className ??= $id;

        $this->definitions[$id] = [
            'factory' => fn (self $container) => $container->build($className),
            'shared' => $shared,
        ];
    }

    public function has(string $id): bool
    {
        return isset($this->definitions[$id]) || isset($this->instances[$id]) || class_exists($id);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->definitions[$id])) {
            $definition = $this->definitions[$id];
            $instance = $definition['factory']($this);

            if ($definition['shared']) {
                $this->instances[$id] = $instance;
            }

            return $instance;
        }

        if (class_exists($id)) {
            $instance = $this->build($id);
            $this->instances[$id] = $instance;
            return $instance;
        }

        throw new RuntimeException(sprintf('Service introuvable: %s', $id));
    }

    public function build(string $className): object
    {
        $reflection = new ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException(sprintf('Classe non instanciable: %s', $className));
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return new $className();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new RuntimeException(sprintf(
                    'Impossible de résoudre %s::$%s',
                    $className,
                    $parameter->getName()
                ));
            }

            $arguments[] = $this->get($type->getName());
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
