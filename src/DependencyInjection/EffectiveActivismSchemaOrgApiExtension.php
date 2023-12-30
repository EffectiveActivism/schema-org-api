<?php declare(strict_types=1);

namespace EffectiveActivism\SchemaOrgApi\DependencyInjection;

use EffectiveActivism\SchemaOrgApi\Resolver\FieldResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class EffectiveActivismSchemaOrgApiExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $containerBuilder)
    {
        // TODO: Implement load() method.
    }

    public function prepend(ContainerBuilder $containerBuilder)
    {
        $bundles = $containerBuilder->getParameter('kernel.bundles');
        if (!isset($bundles['OverblogGraphQLBundle'])) {
            $config = [
                'security' => [
                    'enable_introspection' => false,
                ],
                'definitions' => [
                    'default_field_resolver' => FieldResolver::class,
                    'schema' => [
                        'Query' => [
                            'query' => 'Query',
                            'mutation' => 'Mutation',
                        ],
                    ],
                    'mappings' => [
                        'types' => [
                            [
                                'type' => 'yaml',
                                'dir' => $containerBuilder->get('kernel')->locateResource('@EffectiveActivismSchemaApiBundle/Resources/config/graphql'),
                            ],
                        ],
                    ],
                ]
            ];
            foreach ($containerBuilder->getExtensions() as $name => $extension) {
                match ($name) {
                    'overblog_graphql' => $containerBuilder->prependExtensionConfig($name, $config),
                    default => null
                };
            }
        }
    }
}