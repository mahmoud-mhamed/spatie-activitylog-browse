<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class RelationDiscovery
{
    protected static array $cache = [];

    /**
     * Discover Eloquent relation methods on a model.
     *
     * @return string[]
     */
    public static function getRelations(Model $model): array
    {
        $class = get_class($model);

        if (isset(static::$cache[$class])) {
            return static::$cache[$class];
        }

        $relations = [];
        $reflection = new ReflectionClass($model);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()) {
                continue;
            }

            $declaringClass = $method->getDeclaringClass()->getName();
            if (str_starts_with($declaringClass, 'Illuminate\\') || str_starts_with($declaringClass, 'Symfony\\')) {
                continue;
            }

            if ($method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $name = $method->getName();
            if (
                str_starts_with($name, '__')
                || str_starts_with($name, 'scope')
                || str_starts_with($name, 'boot')
                || (str_starts_with($name, 'get') && str_ends_with($name, 'Attribute'))
                || (str_starts_with($name, 'set') && str_ends_with($name, 'Attribute'))
            ) {
                continue;
            }

            $returnType = $method->getReturnType();

            if (! $returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
                continue;
            }

            $typeName = $returnType->getName();

            if (is_subclass_of($typeName, Relation::class)
                && $typeName !== MorphTo::class
                && ! is_subclass_of($typeName, MorphTo::class)) {
                $relations[] = $name;
            }
        }

        sort($relations);

        return static::$cache[$class] = $relations;
    }
}
