<?php declare(strict_types=1);

namespace EffectiveActivism\SchemaOrgApi\Registry;

use EffectiveActivism\SchemaOrgApi\Constant;
use EffectiveActivism\SchemaOrgApi\Exception\SchemaOrgApiException;
use EffectiveActivism\SchemaOrgApi\Helper\SparQlHelper;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class TypeRegistry
{
    protected static array $mutationArguments = [];

    protected static array $queryArguments = [];

    protected static array $types = [];

    public function __construct(
        protected CacheInterface $cache,
        protected SparQlHelper $sparQlHelper,
        protected LoggerInterface $logger
    )
    {
    }

    public function getRootQuery(): ObjectType
    {
        return new ObjectType([
            'name' => Constant::GRAPHQL_QUERY,
            'fields' => fn (): array => $this->getTypes(true),
        ]);
    }

    public function getRootMutation(): ObjectType
    {
        return new ObjectType([
            'name' => Constant::GRAPHQL_MUTATION,
            'fields' => fn (): array => $this->getTypes(false),
        ]);
    }

    /**
     * @throws SchemaOrgApiException
     */
    public function getType(string $safeTypeName): Type
    {
        if (isset(self::$types[$safeTypeName])) {
            return self::$types[$safeTypeName];
        }
        else {
            try {
                $unsafeTypeName = $this->sparQlHelper->unsafe($safeTypeName);
                $description = null;
                if (!$this->isUnionType($unsafeTypeName)) {
                    list($unsafeTypeName => $description) = $this->cache->get(
                        sprintf('%s_%s', Constant::CACHE_KEY_TYPE, $unsafeTypeName),
                        function (CacheItemInterface $cacheItem) use ($unsafeTypeName) {
                            return $this->sparQlHelper->getClass($unsafeTypeName);
                        }
                    );
                }
                $type = match ($unsafeTypeName) {
                    Constant::SCHEMA_ORG_DATA_TYPE_BOOLEAN => new ObjectType(
                        [
                            'name' => $safeTypeName,
                            'description' => $description,
                            'fields' => [
                                Constant::ARGUMENT_VALUE => Type::boolean(),
                            ],
                        ]
                    ),
                    Constant::SCHEMA_ORG_DATA_TYPE_DATE, Constant::SCHEMA_ORG_DATA_TYPE_DATETIME, Constant::SCHEMA_ORG_DATA_TYPE_TEXT, Constant::SCHEMA_ORG_DATA_TYPE_TIME, Constant::SCHEMA_ORG_DATA_TYPE_URL => new ObjectType(
                        [
                            'name' => $safeTypeName,
                            'description' => $description,
                            'fields' => [
                                Constant::ARGUMENT_VALUE => Type::string(),
                            ],
                        ]
                    ),
                    Constant::SCHEMA_ORG_DATA_TYPE_FLOAT, Constant::SCHEMA_ORG_DATA_TYPE_NUMBER => new ObjectType(
                        [
                            'name' => $safeTypeName,
                            'description' => $description,
                            'fields' => [
                                Constant::ARGUMENT_VALUE => Type::float(),
                            ],
                        ]
                    ),
                    Constant::SCHEMA_ORG_DATA_TYPE_INTEGER => new ObjectType(
                        [
                            'name' => $safeTypeName,
                            'description' => $description,
                            'fields' => [
                                Constant::ARGUMENT_VALUE => Type::int(),
                            ],
                        ]
                    ),
                    default => $this->isUnionType($unsafeTypeName) ?
                        new UnionType([
                            'name' => $unsafeTypeName,
                            'types' => function () use ($unsafeTypeName): array
                            {
                                $fieldName = preg_replace('/Union$/', '', $unsafeTypeName);
                                $types = [];
                                try {
                                    foreach ($this->cache->get(
                                        sprintf('%s_%s', Constant::CACHE_KEY_TYPES, $fieldName),
                                        function (CacheItemInterface $cacheItem) use ($fieldName) {
                                            return $this->sparQlHelper->getRanges($fieldName);
                                        }
                                    ) as $unsafeRangeName => $rangeDescription) {
                                        $safeRangeName = $this->sparQlHelper->safe($unsafeRangeName);
                                        $types[] = $this->getType($safeRangeName);
                                    }
                                } catch (InvalidArgumentException $exception) {
                                    throw new SchemaOrgApiException('Failed to retrieve types', 0, $exception);
                                }
                                return $types;
                            },
                            'resolveType' => function ($value, $context, ResolveInfo $info): Type
                            {
                                return $this->getType(array_key_first($value));
                            },
                        ])
                        :
                        new ObjectType(
                            [
                                'name' => $unsafeTypeName,
                                'description' => $description,
                                'fields' => function () use ($unsafeTypeName): array {
                                    return $this->getFields($unsafeTypeName);
                                },
                            ]
                        ),
                };
                self::$types[$safeTypeName] = $type;
                return $type;
            } catch (InvalidArgumentException $exception) {
                throw new SchemaOrgApiException(sprintf('Failed to retrieve type "%s"', $unsafeTypeName), 0, $exception);
            }
        }
    }

    /**
     * @throws SchemaOrgApiException
     */
    public function getTypes(bool $isQuery): array
    {
        $types = [];
        try {
            foreach ($this->cache->get(
                Constant::CACHE_KEY_TYPES,
                function (CacheItemInterface $cacheItem) {
                    return $this->sparQlHelper->getClasses();
                }
            ) as $unsafeTypeName => $description) {
                $arguments = [];
                try {
                    foreach ($this->cache->get(
                        sprintf('%s_%s', Constant::CACHE_KEY_PROPERTIES, $unsafeTypeName),
                        function (CacheItemInterface $cacheItem) use ($unsafeTypeName) {
                            return $this->sparQlHelper->getProperties($unsafeTypeName);
                        }
                    ) as $fieldName => $fieldDescription) {
                        $arguments[$fieldName] = $isQuery ? $this->getQueryArgument($fieldName, $fieldDescription) : $this->getMutationArgument($fieldName, $fieldDescription);
                    }
                } catch (InvalidArgumentException $exception) {
                    throw new SchemaOrgApiException(sprintf('Failed to retrieve arguments for "%s"', $unsafeTypeName), 0, $exception);
                }
                $safeTypeName = $this->sparQlHelper->safe($unsafeTypeName);
                $types[$safeTypeName] = [
                    'type' => Type::listOf($this->getType($safeTypeName)),
                    'args' => $arguments,
                ];
            }
        } catch (InvalidArgumentException $exception) {
            throw new SchemaOrgApiException('Failed to retrieve types', 0, $exception);
        }
        return $types;
    }

    /**
     * @throws SchemaOrgApiException
     */
    public function getFields(string $unsafeTypeName): array
    {
        $fields = [];
        try {
            foreach ($this->cache->get(
                sprintf('%s_%s', Constant::CACHE_KEY_PROPERTIES, $unsafeTypeName),
                function (CacheItemInterface $cacheItem) use ($unsafeTypeName) {
                    return $this->sparQlHelper->getProperties($unsafeTypeName);
                }
            ) as $fieldName => $fieldData) {
                $fields[$fieldName] = Type::listOf($this->getType(sprintf('%sUnion', $fieldName)));
            }
        } catch (InvalidArgumentException $exception) {
            throw new SchemaOrgApiException(sprintf('Failed to retrieve fields for "%s"', $unsafeTypeName), 0, $exception);
        }
        return $fields;
    }

    public function getQueryArgument(string $unsafeInputName, string $description): array
    {
        if (!isset(self::$queryArguments[$unsafeInputName])) {
            self::$queryArguments[$unsafeInputName] = match ($unsafeInputName) {
                Constant::SCHEMA_ORG_DATA_TYPE_BOOLEAN => [
                    'type' => new InputObjectType([
                        'description' => $description,
                        'name' => sprintf('%sInput', $unsafeInputName),
                        'fields' => [
                            Constant::ARGUMENT_EQUAL_TO => Type::boolean(),
                            Constant::ARGUMENT_NOT_EQUAL_TO => Type::boolean(),
                        ],
                    ]),
                ],
                Constant::SCHEMA_ORG_DATA_TYPE_DATE, Constant::SCHEMA_ORG_DATA_TYPE_DATETIME, Constant::SCHEMA_ORG_DATA_TYPE_TIME => [
                    'type' => new InputObjectType([
                        'description' => $description,
                        'name' => sprintf('%sInput', $unsafeInputName),
                        'fields' => [
                            Constant::ARGUMENT_EQUAL_TO => Type::string(),
                            Constant::ARGUMENT_GREATER_THAN => Type::string(),
                            Constant::ARGUMENT_LESS_THAN => Type::string(),
                            Constant::ARGUMENT_NOT_EQUAL_TO => Type::string(),
                        ],
                    ]),
                ],
                Constant::SCHEMA_ORG_DATA_TYPE_FLOAT, Constant::SCHEMA_ORG_DATA_TYPE_NUMBER => [
                    'type' => new InputObjectType([
                        'description' => $description,
                        'name' => sprintf('%sInput', $unsafeInputName),
                        'fields' => [
                            Constant::ARGUMENT_EQUAL_TO => Type::float(),
                            Constant::ARGUMENT_GREATER_THAN => Type::float(),
                            Constant::ARGUMENT_LESS_THAN => Type::float(),
                            Constant::ARGUMENT_NOT_EQUAL_TO => Type::float(),
                        ],
                    ]),
                ],
                Constant::SCHEMA_ORG_DATA_TYPE_INTEGER => [
                    'type' => new InputObjectType([
                        'description' => $description,
                        'name' => sprintf('%sInput', $unsafeInputName),
                        'fields' => [
                            Constant::ARGUMENT_EQUAL_TO => Type::int(),
                            Constant::ARGUMENT_GREATER_THAN => Type::int(),
                            Constant::ARGUMENT_LESS_THAN => Type::int(),
                            Constant::ARGUMENT_NOT_EQUAL_TO => Type::int(),
                        ],
                    ]),
                ],
                Constant::SCHEMA_ORG_DATA_TYPE_TEXT, Constant::SCHEMA_ORG_DATA_TYPE_URL => [
                    'type' => new InputObjectType([
                        'description' => $description,
                        'name' => sprintf('%sInput', $unsafeInputName),
                        'fields' => [
                            Constant::ARGUMENT_CONTAINS => Type::string(),
                            Constant::ARGUMENT_EQUAL_TO => Type::string(),
                            Constant::ARGUMENT_NOT_EQUAL_TO => Type::string(),
                        ],
                    ]),
                ],
                default =>  [
                    'type' => new InputObjectType([
                        'description' => $description,
                        'name' => sprintf('%sInput', $unsafeInputName),
                        'fields' => function () use ($unsafeInputName): array
                        {
                            $fields = [];
                            try {
                                // Check if field is a class or property. Classes are uppercase, properties are lowercase.
                                if (preg_match('~^\p{Lu}~u', $unsafeInputName)) {
                                    foreach ($this->cache->get(
                                        sprintf('%s_%s', Constant::CACHE_KEY_PROPERTIES, $unsafeInputName),
                                        function (CacheItemInterface $cacheItem) use ($unsafeInputName) {
                                            return $this->sparQlHelper->getProperties($unsafeInputName);
                                        }
                                    ) as $fieldName => $fieldData) {
                                        $fields[$fieldName] = $this->getQueryArgument($fieldName, $fieldData);
                                    }
                                }
                                else {
                                    foreach ($this->cache->get(
                                        sprintf('%s_%s', Constant::CACHE_KEY_TYPES, $unsafeInputName),
                                        function (CacheItemInterface $cacheItem) use ($unsafeInputName) {
                                            return $this->sparQlHelper->getRanges($unsafeInputName);
                                        }
                                    ) as $unsafeRangeName => $rangeDescription) {
                                        $safeRangeName = $this->sparQlHelper->safe($unsafeRangeName);
                                        $fields[$safeRangeName] = $this->getQueryArgument($unsafeRangeName, $rangeDescription);
                                    }
                                }
                            } catch (InvalidArgumentException $exception) {
                                throw new SchemaOrgApiException('Failed to retrieve types', 0, $exception);
                            }
                            return $fields;
                        },
                    ]),
                ],
            };
        }
        return self::$queryArguments[$unsafeInputName];
    }

    public function getMutationArgument(string $unsafeInputName, string $description): array
    {
        if (!isset(self::$mutationArguments[$unsafeInputName])) {
            self::$mutationArguments[$unsafeInputName] = match ($unsafeInputName) {
                Constant::SCHEMA_ORG_DATA_TYPE_BOOLEAN => [
                    'type' => new InputObjectType([
                        'description' => $description,
                        'name' => sprintf('%sMutationInput', $unsafeInputName),
                        'fields' => [
                            Constant::ARGUMENT_EQUAL_TO => Type::boolean(),
                            Constant::ARGUMENT_NOT_EQUAL_TO => Type::boolean(),
                        ],
                    ]),
                ],
                Constant::SCHEMA_ORG_DATA_TYPE_DATE, Constant::SCHEMA_ORG_DATA_TYPE_DATETIME, Constant::SCHEMA_ORG_DATA_TYPE_TIME => [
                    'type' => new InputObjectType([
                        'description' => $description,
                        'name' => sprintf('%sMutationInput', $unsafeInputName),
                        'fields' => [
                            Constant::ARGUMENT_EQUAL_TO => Type::string(),
                            Constant::ARGUMENT_GREATER_THAN => Type::string(),
                            Constant::ARGUMENT_LESS_THAN => Type::string(),
                            Constant::ARGUMENT_NOT_EQUAL_TO => Type::string(),
                        ],
                    ]),
                ],
                Constant::SCHEMA_ORG_DATA_TYPE_FLOAT, Constant::SCHEMA_ORG_DATA_TYPE_NUMBER => [
                    'type' => new InputObjectType([
                        'description' => $description,
                        'name' => sprintf('%sMutationInput', $unsafeInputName),
                        'fields' => [
                            Constant::ARGUMENT_EQUAL_TO => Type::float(),
                            Constant::ARGUMENT_GREATER_THAN => Type::float(),
                            Constant::ARGUMENT_LESS_THAN => Type::float(),
                            Constant::ARGUMENT_NOT_EQUAL_TO => Type::float(),
                        ],
                    ]),
                ],
                Constant::SCHEMA_ORG_DATA_TYPE_INTEGER => [
                    'type' => new InputObjectType([
                        'description' => $description,
                        'name' => sprintf('%sMutationInput', $unsafeInputName),
                        'fields' => [
                            Constant::ARGUMENT_EQUAL_TO => Type::int(),
                            Constant::ARGUMENT_GREATER_THAN => Type::int(),
                            Constant::ARGUMENT_LESS_THAN => Type::int(),
                            Constant::ARGUMENT_NOT_EQUAL_TO => Type::int(),
                        ],
                    ]),
                ],
                Constant::SCHEMA_ORG_DATA_TYPE_TEXT, Constant::SCHEMA_ORG_DATA_TYPE_URL => [
                    'type' => new InputObjectType([
                        'description' => $description,
                        'name' => sprintf('%sMutationInput', $unsafeInputName),
                        'fields' => [
                            Constant::ARGUMENT_CONTAINS => Type::string(),
                            Constant::ARGUMENT_EQUAL_TO => Type::string(),
                            Constant::ARGUMENT_NOT_EQUAL_TO => Type::string(),
                        ],
                    ]),
                ],
                default =>  [
                    'type' => new InputObjectType([
                        'description' => $description,
                        'name' => sprintf('%sMutationInput', $unsafeInputName),
                        'fields' => function () use ($unsafeInputName): array
                        {
                            $fields = [];
                            $mutationFields = [];
                            try {
                                // Check if field is a class or property. Classes are uppercase, properties are lowercase.
                                if (preg_match('~^\p{Lu}~u', $unsafeInputName)) {
                                    foreach ($this->cache->get(
                                        sprintf('%s_%s', Constant::CACHE_KEY_PROPERTIES, $unsafeInputName),
                                        function (CacheItemInterface $cacheItem) use ($unsafeInputName) {
                                            return $this->sparQlHelper->getProperties($unsafeInputName);
                                        }
                                    ) as $fieldName => $fieldData) {
                                        $fields[$fieldName] = $this->getMutationArgument($fieldName, $fieldData);
                                    }
                                }
                                else {
                                    foreach ($this->cache->get(
                                        sprintf('%s_%s', Constant::CACHE_KEY_TYPES, $unsafeInputName),
                                        function (CacheItemInterface $cacheItem) use ($unsafeInputName) {
                                            return $this->sparQlHelper->getRanges($unsafeInputName);
                                        }
                                    ) as $unsafeRangeName => $rangeDescription) {
                                        $safeRangeName = $this->sparQlHelper->safe($unsafeRangeName);
                                        $fields[$safeRangeName] = $this->getMutationArgument($unsafeRangeName, $rangeDescription);
                                        $mutationFields[$safeRangeName] = match ($unsafeRangeName) {
                                            Constant::SCHEMA_ORG_DATA_TYPE_BOOLEAN => [
                                                'type' => new InputObjectType([
                                                    'description' => $rangeDescription,
                                                    'name' => sprintf('%s%sInlineMutationInput', $unsafeInputName, $unsafeRangeName),
                                                    'fields' => [
                                                        Constant::ARGUMENT_VALUE => Type::boolean(),
                                                    ],
                                                ]),
                                            ],
                                            Constant::SCHEMA_ORG_DATA_TYPE_DATE,
                                            Constant::SCHEMA_ORG_DATA_TYPE_DATETIME,
                                            Constant::SCHEMA_ORG_DATA_TYPE_TIME,
                                            Constant::SCHEMA_ORG_DATA_TYPE_TEXT,
                                            Constant::SCHEMA_ORG_DATA_TYPE_URL=> [
                                                'type' => new InputObjectType([
                                                    'description' => $rangeDescription,
                                                    'name' => sprintf('%s%sInlineMutationInput', $unsafeInputName, $unsafeRangeName),
                                                    'fields' => [
                                                        Constant::ARGUMENT_VALUE => Type::string(),
                                                    ],
                                                ]),
                                            ],
                                            Constant::SCHEMA_ORG_DATA_TYPE_FLOAT, Constant::SCHEMA_ORG_DATA_TYPE_NUMBER => [
                                                'type' => new InputObjectType([
                                                    'description' => $rangeDescription,
                                                    'name' => sprintf('%s%sInlineMutationInput', $unsafeInputName, $unsafeRangeName),
                                                    'fields' => [
                                                        Constant::ARGUMENT_VALUE => Type::float(),
                                                    ],
                                                ]),
                                            ],
                                            Constant::SCHEMA_ORG_DATA_TYPE_INTEGER => [
                                                'type' => new InputObjectType([
                                                    'description' => $rangeDescription,
                                                    'name' => sprintf('%s%sInlineMutationInput', $unsafeInputName, $unsafeRangeName),
                                                    'fields' => [
                                                        Constant::ARGUMENT_VALUE => Type::int(),
                                                    ],
                                                ]),
                                            ],
                                            default => $this->getMutationArgument($unsafeRangeName, $rangeDescription),
                                        };
                                    }
                                    $insertInputType = sprintf('%s%s', Constant::ARGUMENT_INSERT, $unsafeInputName);
                                    if (!isset(self::$mutationArguments[$insertInputType])) {
                                        self::$mutationArguments[$insertInputType] = [
                                            'type' => new InputObjectType([
                                                'description' => $insertInputType,
                                                'name' => sprintf('%s%s', Constant::ARGUMENT_INSERT, $unsafeInputName),
                                                'fields' => $mutationFields,
                                            ]),
                                        ];
                                    }
                                    $fields[Constant::ARGUMENT_INSERT] = self::$mutationArguments[$insertInputType];
                                    $replaceInputType = sprintf('%s%s', Constant::ARGUMENT_REPLACE, $unsafeInputName);
                                    if (!isset(self::$mutationArguments[$replaceInputType])) {
                                        self::$mutationArguments[$replaceInputType] = [
                                            'type' => new InputObjectType([
                                                'description' => sprintf('Replace existing entities of type %s limited by matching criteria', $unsafeInputName),
                                                'name' => $replaceInputType,
                                                'fields' => $mutationFields,
                                            ]),
                                        ];
                                    }
                                    $fields[Constant::ARGUMENT_REPLACE] = self::$mutationArguments[$replaceInputType];
                                    $replaceAllInputType = sprintf('%s%s', Constant::ARGUMENT_REPLACE_ALL, $unsafeInputName);
                                    if (!isset(self::$mutationArguments[$replaceAllInputType])) {
                                        self::$mutationArguments[$replaceAllInputType] = [
                                            'type' => new InputObjectType([
                                                'description' => sprintf('Replace all existing entities of type %s', $unsafeInputName),
                                                'name' => $replaceAllInputType,
                                                'fields' => $mutationFields,
                                            ]),
                                        ];
                                    }
                                    $fields[Constant::ARGUMENT_REPLACE_ALL] = self::$mutationArguments[$replaceAllInputType];
                                    if (!isset(self::$mutationArguments[Constant::ARGUMENT_DETACH])) {
                                        self::$mutationArguments[Constant::ARGUMENT_DETACH] = [
                                            'type' => new InputObjectType([
                                                'description' => 'Detach existing entities limited by matching criteria',
                                                'name' => Constant::ARGUMENT_DETACH,
                                                'fields' => [
                                                    'confirm' => Type::boolean(),
                                                ],
                                            ]),
                                        ];
                                    }
                                    $fields[Constant::ARGUMENT_DETACH] = self::$mutationArguments[Constant::ARGUMENT_DETACH];
                                    if (!isset(self::$mutationArguments[Constant::ARGUMENT_DETACH_ALL])) {
                                        self::$mutationArguments[Constant::ARGUMENT_DETACH_ALL] = [
                                            'type' => new InputObjectType([
                                                'description' => 'Detach all existing entities',
                                                'name' => Constant::ARGUMENT_DETACH_ALL,
                                                'fields' => [
                                                    'confirm' => Type::boolean(),
                                                ],
                                            ]),
                                        ];
                                    }
                                    $fields[Constant::ARGUMENT_DETACH_ALL] = self::$mutationArguments[Constant::ARGUMENT_DETACH_ALL];
                                }
                            } catch (InvalidArgumentException $exception) {
                                throw new SchemaOrgApiException('Failed to retrieve types', 0, $exception);
                            }
                            return $fields;
                        },
                    ]),
                ],
            };
        }
        return self::$mutationArguments[$unsafeInputName];
    }

    protected function isUnionType(string $name): bool
    {
        return !preg_match('~^\p{Lu}~u', $name) && str_ends_with($name, 'Union');
    }
}
