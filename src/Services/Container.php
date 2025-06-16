<?php
declare(strict_types=1);

namespace SEOForge\Services;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;

/**
 * Dependency Injection Container
 * 
 * PSR-11 compatible dependency injection container for managing services
 * and their dependencies throughout the plugin lifecycle.
 * 
 * @package SEOForge\Services
 * @since 2.0.0
 */
class Container implements ContainerInterface {
    
    /**
     * Service definitions
     */
    private array $services = [];
    
    /**
     * Singleton instances
     */
    private array $instances = [];
    
    /**
     * Service factories
     */
    private array $factories = [];
    
    /**
     * Register a service with the container
     * 
     * @param string $id Service identifier
     * @param callable|object|string $concrete Service implementation
     * @param bool $singleton Whether to treat as singleton
     */
    public function set(string $id, $concrete, bool $singleton = false): void {
        $this->services[$id] = [
            'concrete' => $concrete,
            'singleton' => $singleton
        ];
        
        // Remove existing instance if re-registering
        unset($this->instances[$id]);
    }
    
    /**
     * Register a singleton service
     * 
     * @param string $id Service identifier
     * @param callable|object|string $concrete Service implementation
     */
    public function singleton(string $id, $concrete): void {
        $this->set($id, $concrete, true);
    }
    
    /**
     * Register a factory service
     * 
     * @param string $id Service identifier
     * @param callable $factory Factory function
     */
    public function factory(string $id, callable $factory): void {
        $this->factories[$id] = $factory;
    }
    
    /**
     * Get a service from the container
     * 
     * @param string $id Service identifier
     * @return mixed Service instance
     * @throws ContainerException If service cannot be resolved
     * @throws NotFoundException If service is not found
     */
    public function get(string $id) {
        if (!$this->has($id)) {
            throw new NotFoundException("Service '{$id}' not found in container");
        }
        
        // Return existing singleton instance
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        
        try {
            $instance = $this->resolve($id);
            
            // Store singleton instances
            if (isset($this->services[$id]) && $this->services[$id]['singleton']) {
                $this->instances[$id] = $instance;
            }
            
            return $instance;
            
        } catch (\Throwable $e) {
            throw new ContainerException(
                "Failed to resolve service '{$id}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    /**
     * Check if a service exists in the container
     * 
     * @param string $id Service identifier
     * @return bool
     */
    public function has(string $id): bool {
        return isset($this->services[$id]) || isset($this->factories[$id]) || class_exists($id);
    }
    
    /**
     * Resolve a service instance
     * 
     * @param string $id Service identifier
     * @return mixed Service instance
     * @throws \ReflectionException
     */
    private function resolve(string $id) {
        // Check for factory
        if (isset($this->factories[$id])) {
            return call_user_func($this->factories[$id], $this);
        }
        
        // Check for registered service
        if (isset($this->services[$id])) {
            $concrete = $this->services[$id]['concrete'];
            
            if (is_callable($concrete)) {
                return call_user_func($concrete, $this);
            }
            
            if (is_object($concrete)) {
                return $concrete;
            }
            
            if (is_string($concrete)) {
                return $this->build($concrete);
            }
        }
        
        // Auto-resolve class if it exists
        if (class_exists($id)) {
            return $this->build($id);
        }
        
        throw new NotFoundException("Unable to resolve service '{$id}'");
    }
    
    /**
     * Build a class instance with dependency injection
     * 
     * @param string $className Class name to build
     * @return object Class instance
     * @throws \ReflectionException
     */
    private function build(string $className): object {
        $reflection = new \ReflectionClass($className);
        
        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Class '{$className}' is not instantiable");
        }
        
        $constructor = $reflection->getConstructor();
        
        if ($constructor === null) {
            return new $className();
        }
        
        $parameters = $constructor->getParameters();
        $dependencies = [];
        
        foreach ($parameters as $parameter) {
            $dependency = $this->resolveDependency($parameter);
            $dependencies[] = $dependency;
        }
        
        return $reflection->newInstanceArgs($dependencies);
    }
    
    /**
     * Resolve a constructor parameter dependency
     * 
     * @param \ReflectionParameter $parameter Parameter to resolve
     * @return mixed Resolved dependency
     * @throws ContainerException
     */
    private function resolveDependency(\ReflectionParameter $parameter) {
        $type = $parameter->getType();
        
        if ($type === null) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            
            throw new ContainerException(
                "Cannot resolve parameter '{$parameter->getName()}' without type hint"
            );
        }
        
        if ($type instanceof \ReflectionNamedType) {
            $typeName = $type->getName();
            
            // Handle built-in types
            if ($type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }
                
                throw new ContainerException(
                    "Cannot resolve built-in type '{$typeName}' for parameter '{$parameter->getName()}'"
                );
            }
            
            // Resolve class dependency
            if ($this->has($typeName)) {
                return $this->get($typeName);
            }
            
            if ($parameter->allowsNull()) {
                return null;
            }
            
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            
            throw new ContainerException(
                "Cannot resolve dependency '{$typeName}' for parameter '{$parameter->getName()}'"
            );
        }
        
        throw new ContainerException(
            "Cannot resolve complex type for parameter '{$parameter->getName()}'"
        );
    }
    
    /**
     * Remove a service from the container
     * 
     * @param string $id Service identifier
     */
    public function remove(string $id): void {
        unset($this->services[$id], $this->instances[$id], $this->factories[$id]);
    }
    
    /**
     * Clear all services from the container
     */
    public function clear(): void {
        $this->services = [];
        $this->instances = [];
        $this->factories = [];
    }
    
    /**
     * Get all registered service IDs
     * 
     * @return array Service identifiers
     */
    public function getServiceIds(): array {
        return array_unique(array_merge(
            array_keys($this->services),
            array_keys($this->factories)
        ));
    }
}

/**
 * Container Exception
 */
class ContainerException extends \Exception implements ContainerExceptionInterface {}

/**
 * Not Found Exception
 */
class NotFoundException extends \Exception implements NotFoundExceptionInterface {}