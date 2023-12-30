<?php declare(strict_types=1);

namespace EffectiveActivism\SchemaOrgApi\Exception;

use Exception;
use GraphQL\Error\ClientAware;

class SchemaOrgApiException extends Exception implements ClientAware
{

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return 'businessLogic';
    }
}
