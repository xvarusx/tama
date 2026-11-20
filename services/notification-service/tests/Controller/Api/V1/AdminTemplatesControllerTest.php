<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Entity\NotificationTemplate;
use App\Enum\Channel;
use App\Security\Caller;
use App\Tests\Support\FakeAuthServiceClient;
use App\Utils\DemoUserUuids;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AdminTemplatesControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private FakeAuthServiceClient $auth;
    private NotificationTemplate $template;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $container = $this->client->getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConfiguration()->setUseSchemaEditorApi(true);
        new SchemaTool($this->em)->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->auth = $container->get(FakeAuthServiceClient::class);
        $this->asAdmin();

        $this->template = new NotificationTemplate('invoice.issued', Channel::EMAIL, 'en', 'Invoice {{ number }}', '<p>{{ number }}</p>');
        $this->em->persist($this->template);
        $this->em->flush();
    }

    public function testCreateTemplateRejectsDuplicateNaturalKey(): void
    {
        $this->request('POST', '/api/v1/admin/templates', [
            'type' => 'invoice.issued',
            'channel' => 'email',
            'locale' => 'en',
            'subject' => 'Duplicate',
            'bodyTemplate' => '<p>Duplicate</p>',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('TEMPLATE_ALREADY_EXISTS', $this->json()['error']['code']);
    }

    public function testUpdateTemplateChangesSubjectAndBody(): void
    {
        $url = '/api/v1/admin/templates/' . (string)$this->template->getId();
        $this->request('PUT', $url, [
            'type' => 'invoice.issued',
            'channel' => 'email',
            'locale' => 'en',
            'subject' => 'Updated subject',
            'bodyTemplate' => '<p>Updated body</p>',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('Updated subject', $this->json()['data']['attributes']['subject']);

        $this->request('GET', $url);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('<p>Updated body</p>', $this->json()['data']['attributes']['bodyTemplate']);
    }

    public function testDeleteTemplateReturns204(): void
    {
        $this->client->request(
            'DELETE',
            '/api/v1/admin/templates/' . (string)$this->template->getId(),
            server: ['HTTP_X_AUTH_TOKEN' => 'valid-token'],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(0, $this->em->getRepository(NotificationTemplate::class)->count([]));
    }

    public function testUnknownTemplateIdReturns404(): void
    {
        $this->request('GET', '/api/v1/admin/templates/00000000-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('TEMPLATE_NOT_FOUND', $this->json()['error']['code']);
    }

    public function testNonAdminCannotManageTemplatesOrSettings(): void
    {
        $this->auth->setCaller(new Caller(DemoUserUuids::forNumber(1), 'jane@example.com', 'Jane Doe', ['ROLE_USER']));

        $this->request('GET', '/api/v1/admin/templates');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('FORBIDDEN', $this->json()['error']['code']);

        $this->request('GET', '/api/v1/admin/settings');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function asAdmin(): void
    {
        $this->auth->setCaller(new Caller(DemoUserUuids::forNumber(1), 'admin@example.com', 'Admin', ['ROLE_USER', 'ROLE_ADMIN']));
    }

    /** @param array<string, mixed>|null $payload */
    private function request(string $method, string $url, ?array $payload = null): void
    {
        $this->client->request(
            $method,
            $url,
            server: ['HTTP_X_AUTH_TOKEN' => 'valid-token', 'CONTENT_TYPE' => 'application/json'],
            content: $payload === null ? null : json_encode($payload),
        );
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        return json_decode((string)$this->client->getResponse()->getContent(), true);
    }
}
