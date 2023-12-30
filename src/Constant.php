<?php declare(strict_types=1);

namespace EffectiveActivism\SchemaOrgApi;

class Constant
{
    const SCHEMA_API = 'schema_api';

    const CACHE_KEY_TYPE = self::SCHEMA_API . '_type';

    const CACHE_KEY_TYPES = self::SCHEMA_API . '_types';

    const CACHE_KEY_PROPERTIES = self::SCHEMA_API . '_properties';

    const PENDING_ADDRESS = 'https://pending.schema.org';

    const BATCH_SIZE = 100;

    const MAX_DEPTH = 100;

    const XSD_DATE_FORMATS = [
        'date' => 'Y-m-dp',
        'dateTime' => 'Y-m-d\TH:i:s.vp',
        'time' => 'H:i:sp',
    ];

    const GRAPHQL_QUERY = 'Query';
    const GRAPHQL_MUTATION = 'Mutation';

    const SCHEMA_ORG_API_IDENTIFIER = 'identifier';
    const SCHEMA_ORG_API_IDENTIFIER_DESCRIPTION = 'An urn of the format 82912fac-6d90-11ed-aea9-4709972f160b';

    const SCHEMA_ORG_DATA_TYPE_BOOLEAN = 'Boolean';
    const SCHEMA_ORG_DATA_TYPE_DATE = 'Date';
    const SCHEMA_ORG_DATA_TYPE_DATETIME = 'DateTime';
    const SCHEMA_ORG_DATA_TYPE_FLOAT = 'Float';
    const SCHEMA_ORG_DATA_TYPE_INTEGER = 'Integer';
    const SCHEMA_ORG_DATA_TYPE_NUMBER = 'Number';
    const SCHEMA_ORG_DATA_TYPE_TEXT = 'Text';
    const SCHEMA_ORG_DATA_TYPE_TIME = 'Time';
    const SCHEMA_ORG_DATA_TYPE_URL = 'URL';

    // TODO: This is not all schema.org data types. Consider adding the remaining types.
    const SCHEMA_ORG_DATA_TYPES = [
        Constant::SCHEMA_ORG_DATA_TYPE_BOOLEAN => 'BooleanType',
        Constant::SCHEMA_ORG_DATA_TYPE_DATE => 'DateType',
        Constant::SCHEMA_ORG_DATA_TYPE_DATETIME => 'DateTimeType',
        Constant::SCHEMA_ORG_DATA_TYPE_FLOAT => 'FloatType',
        Constant::SCHEMA_ORG_DATA_TYPE_INTEGER => 'IntegerType',
        Constant::SCHEMA_ORG_DATA_TYPE_NUMBER => 'NumberType',
        Constant::SCHEMA_ORG_DATA_TYPE_TEXT => 'TextType',
        Constant::SCHEMA_ORG_DATA_TYPE_TIME => 'Time',
        Constant::SCHEMA_ORG_DATA_TYPE_URL => 'URLType',
    ];

    const GRAPHQL_RESERVED_TYPES = [
        'Int',
        'Float',
        'Boolean',
        'String',
        'ID',
        'null',
        'true',
        'false',
    ];

    // Query arguments.
    const ARGUMENT_CONTAINS = 'contains';
    const ARGUMENT_EQUAL_TO = 'equalTo';
    const ARGUMENT_GREATER_THAN = 'greaterThan';
    const ARGUMENT_LESS_THAN = 'lessThan';
    const ARGUMENT_NOT_EQUAL_TO = 'notEqualTo';

    const QUERY_ARGUMENTS = [
        Constant::ARGUMENT_CONTAINS,
        Constant::ARGUMENT_EQUAL_TO,
        Constant::ARGUMENT_GREATER_THAN,
        Constant::ARGUMENT_LESS_THAN,
        Constant::ARGUMENT_NOT_EQUAL_TO,
    ];

    // Mutation arguments.
    // TODO: Consider adding 'insertIfEmpty' or 'insertIfNotFound' or 'upsert' to further minimize calls to backend.
    const ARGUMENT_INSERT = 'insert';
    const ARGUMENT_DETACH = 'detach';
    const ARGUMENT_DETACH_ALL = 'detachAll';
    const ARGUMENT_REPLACE = 'replaceWith';
    const ARGUMENT_REPLACE_ALL = 'replaceAllWith';
    // TODO: 'value' is used by schema.org, so consider using something else.
    // TODO: Otherwise, various code must have built in guard rails.
    const ARGUMENT_VALUE = 'value';

    const MUTATION_ARGUMENTS = [
        Constant::ARGUMENT_DETACH,
        Constant::ARGUMENT_DETACH_ALL,
        Constant::ARGUMENT_INSERT,
        Constant::ARGUMENT_REPLACE,
        Constant::ARGUMENT_REPLACE_ALL,
    ];
}
