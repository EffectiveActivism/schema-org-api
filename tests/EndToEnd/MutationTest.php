<?php

namespace EffectiveActivism\SchemaOrgApi\Tests\EndToEnd;

use EffectiveActivism\SchemaOrgApi\Tests\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MutationTest extends WebTestCase
{
    public static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testSetPerson()
    {
        $client = self::createClient();
        $httpClient = new MockHttpClient(
            [
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-classes.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-properties-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-comment-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-types-of-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-properties-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-prefix-for-TextType.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-comment-for-TextType.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-types-of-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-prefix-for-name.xml')),
                new MockResponse(''),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetPerson/result.xml')),
            ]
        );
        self::$kernel->getContainer()->set(HttpClientInterface::class, $httpClient);
        $client->request(
            'POST',
            '/',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            '{ "query": "mutation { Person ( name: { TextType: { equalTo: \"Foo\" } insert: { TextType: { value: \"Bar\" } } } ) { name { ... on TextType { value } } } }" }'
        );
        $response = $client->getInternalResponse();
        $this->assertEquals('{"data":{"Person":[{"name":[{"value":"Foo"}]}]}}', $response->getContent());
    }

    public function testSetOrganizationOfPerson()
    {
        $client = self::createClient();
        $httpClient = new MockHttpClient(
            [
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-classes.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-properties-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-comment-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-types-of-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-properties-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-types-of-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-properties-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-comment-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-properties-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-types-of-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-TextType.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-comment-for-TextType.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-types-of-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-name.xml')),
                new MockResponse(''),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetOrganizationOfPerson/result.xml')),
            ]
        );
        self::$kernel->getContainer()->set(HttpClientInterface::class, $httpClient);
        $client->request(
            'POST',
            '/',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            '{ "query": "mutation { Person ( memberOf: { Organization: { name: { TextType: { equalTo: \"Bar\" } insert: { TextType: { value: \"Baz\" } } } } } ) { memberOf { ... on Organization { name { ... on TextType { value } } } } } }" }'
        );
        $response = $client->getInternalResponse();
        $this->assertEquals('{"data":{"Person":[{"memberOf":[{"name":[{"value":"Bar"},{"value":"Baz"}]}]}]}}', $response->getContent());
    }

    public function testSetKnowsOfPersonOfOrganization()
    {
        $client = self::createClient();
        $httpClient = new MockHttpClient(
            [
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-classes.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-properties-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-comment-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-types-of-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-properties-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-types-of-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-knows.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-types-of-knows.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-properties-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-properties-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-knows.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-types-of-knows.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-TextType.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-comment-for-TextType.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-types-of-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-knows.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-memberOf.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-Organization.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-name.xml')),
                new MockResponse(''),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-knows.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-Person.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/get-prefix-for-name.xml')),
                new MockResponse(file_get_contents(__DIR__.'/../fixtures/testSetKnowsOfPersonOfOrganization/result.xml')),
            ],
        );
        self::$kernel->getContainer()->set(HttpClientInterface::class, $httpClient);
        $client->request(
            'POST',
            '/',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            '{ "query": "mutation { Person ( memberOf: { Organization: { name: { TextType: { equalTo: \"Bar\" } } } } knows: { insert: { Person: { name: { TextType: { equalTo: \"Foo\" } } } } } ) { knows { ... on Person { name { ... on TextType { value } } } } } }" }'
        );
        $response = $client->getInternalResponse();
        $this->assertEquals('{"data":{"Person":[{"knows":[{"name":[{"value":"Foo"}]}]}]}}', $response->getContent());
    }
}
