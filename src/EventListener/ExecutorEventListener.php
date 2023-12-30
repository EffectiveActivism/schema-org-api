<?php declare(strict_types=1);

namespace EffectiveActivism\SchemaOrgApi\EventListener;

use EffectiveActivism\SchemaOrgApi\Registry\TypeRegistry;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\SchemaConfig;
use Overblog\GraphQLBundle\Definition\Type\ExtensibleSchema;
use Overblog\GraphQLBundle\Event\ExecutorArgumentsEvent;

class ExecutorEventListener
{
    protected static SchemaConfig $schemaConfig;

    public function __construct(TypeRegistry $typeRegistry)
    {
        if (!isset(self::$schemaConfig)) {
            self::$schemaConfig = SchemaConfig::create()
                ->setQuery($typeRegistry->getRootQuery())
                ->setMutation($typeRegistry->getRootMutation())
                ->setTypeLoader(static fn (string $name): Type => $typeRegistry->getType($name));
        }
    }

    public function onPreExecutor(ExecutorArgumentsEvent $event)
    {
        $event->setSchema(new ExtensibleSchema(self::$schemaConfig));
    }
}
