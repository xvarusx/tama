<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class PreviewControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = $this->em();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function em(): EntityManagerInterface
    {
        return $this->client->getContainer()->get('doctrine')->getManager();
    }

    public function testSlugPreviewRejectsAnUnknownEntity(): void
    {
        $this->client->request('GET', '/api/v1/slugs/preview?entity=product&name=Widget');

        // MapQueryString uses 404 as its validation-failure status by default.
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testSlugPreviewReturnsASlugForABrand(): void
    {
        $this->client->request('GET', '/api/v1/slugs/preview?entity=brand&name=Widget');

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('widget', $body['data']['slug']);
    }
}
