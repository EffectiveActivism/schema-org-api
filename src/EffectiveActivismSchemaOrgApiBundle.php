<?php declare(strict_types = 1);

namespace EffectiveActivism\SchemaOrgApi;

use EffectiveActivism\SchemaOrgApi\DependencyInjection\EffectiveActivismSchemaOrgApiExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EffectiveActivismSchemaOrgApiBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new EffectiveActivismSchemaOrgApiExtension();
    }
}
