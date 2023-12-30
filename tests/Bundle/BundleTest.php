<?php declare(strict_types=1);

namespace EffectiveActivism\SchemaOrgApi\Tests\Bundle;

use EffectiveActivism\SchemaOrgApi\DependencyInjection\EffectiveActivismSchemaOrgApiExtension;
use EffectiveActivism\SchemaOrgApi\EffectiveActivismSchemaOrgApiBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BundleTest extends KernelTestCase
{
    public function testBundle()
    {
        $bundle = new EffectiveActivismSchemaOrgApiBundle();
        $this->assertInstanceOf(EffectiveActivismSchemaOrgApiExtension::class, $bundle->getContainerExtension());
    }
}
