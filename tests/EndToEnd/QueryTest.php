<?php

namespace EffectiveActivism\SchemaOrgApi\Tests\EndToEnd;

use EffectiveActivism\SchemaOrgApi\Tests\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class QueryTest extends WebTestCase
{
    public static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testGetPerson()
    {
        $client = self::createClient();
        $client->getContainer()->set(HttpClientInterface::class, new MockHttpClient(
            [
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-classes.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-properties-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-comment-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-types-of-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-properties-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-prefix-for-TextType.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-comment-for-TextType.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-types-of-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetPerson/result.xml')),
            ]
        ));
        $client->getContainer()->set(TagAwareCacheInterface::class, new TagAwareAdapter(new NullAdapter()));
        $client->request(
            'POST',
            '/',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            '{ "query": "query { Person ( name: { TextType: { equalTo: \"Foo\" } } ) { name { ... on TextType { value } } } }" }'
        );
        $response = $client->getInternalResponse();
        $this->assertEquals('{"data":{"Person":[{"name":[{"value":"Foo"}]}]}}', $response->getContent());
    }

    public function testGetOrganizationOfPerson()
    {
        $client = self::createClient();
        $client->getContainer()->set(HttpClientInterface::class, new MockHttpClient(
            [
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-classes.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-properties-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-comment-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-properties-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-comment-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-types-of-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-properties-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-types-of-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-properties-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-properties-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-types-of-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-TextType.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-comment-for-TextType.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-types-of-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testGetOrganizationOfPerson/result.xml')),
            ]
        ));
        $client->getContainer()->set(TagAwareCacheInterface::class, new TagAwareAdapter(new NullAdapter()));
        $client->request(
            'POST',
            '/',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            '{ "query": "query { Person ( memberOf: { Organization: { name: { TextType: { equalTo: \"Foo\" } } } } ) { memberOf { ... on Organization { name { ... on TextType { value } } } } } }" }'
        );
        $response = $client->getInternalResponse();
        $this->assertEquals('{"data":{"Person":[{"memberOf":[{"name":[{"value":"Foo"}]}]}]}}', $response->getContent());
    }
}
