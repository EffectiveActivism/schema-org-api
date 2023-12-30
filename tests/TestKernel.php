<?php

namespace EffectiveActivism\SchemaOrgApi\Tests;

use EffectiveActivism\SchemaOrgApi\Resolver\FieldResolver;
use EffectiveActivism\SparQlClient\EffectiveActivismSparQlClientBundle;
use Exception;
use Overblog\GraphQLBundle\OverblogGraphQLBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new EffectiveActivismSparQlClientBundle(),
            new OverblogGraphQLBundle(),
            new TestBundle(),
        ];
    }

    /**
     * @throws Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'router' => [
                    'resource' => __DIR__ . '/config/routes.yml',
                    'utf8' => true,
                ],
                'test' => true,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => [
                    'log' => true,
                ],
                'annotations' => [
                    'enabled' => false,
                ],
            ]);
            $container->loadFromExtension('overblog_graphql', [
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
                                'dir' => __DIR__ . '/config/graphql',
                            ],
                        ],
                    ],
                ],
            ]);
            $container->loadFromExtension('sparql_client', [
                'sparql_endpoint' => 'http://test-sparql-endpoint:9999/blazegraph/sparql',
                'namespaces' => [
                    'schema' => 'https://schema.org/',
                ],
            ]);
        });
    }
}
