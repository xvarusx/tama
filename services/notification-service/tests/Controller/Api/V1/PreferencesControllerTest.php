<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Entity\NotificationPreference;
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
use Symfony\Component\Uid\Uuid;

final class PreferencesControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private FakeAuthServiceClient $auth;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $container = $this->client->getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConfiguration()->setUseSchemaEditorApi(true);
        new SchemaTool($this->em)->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->auth = $container->get(FakeAuthServiceClient::class);
        $this->auth->setCaller(new Caller(DemoUserUuids::forNumber(1), 'jane@example.com', 'Jane Doe', ['ROLE_USER']));

        $this->em->persist(new NotificationTemplate('invoice.issued', Channel::EMAIL, 'en', 'Invoice {{ number }}', '<p>Invoice {{ number }}</p>'));
        $this->em->persist(new NotificationTemplate('payment.installment.overdue', Channel::IN_APP, 'en', 'Overdue {{ invoiceNumber }}', 'Overdue'));
        $this->em->flush();
    }

    public function testReturnsDefaultEnabledMatrixForTemplateTypes(): void
    {
        $this->get('/api/v1/preferences');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = $this->json()['data'];
        self::assertCount(2, $data);
        self::assertSame('invoice.issued', $data[0]['type']);
        self::assertSame(['in_app' => true, 'email' => true], $data[0]['channels']);
        self::assertSame('payment.installment.overdue', $data[1]['type']);
        self::assertSame(['in_app' => true, 'email' => true], $data[1]['channels']);
    }

    public function testUpsertsPreferencesForCallerOnly(): void
    {
        $otherUser = new NotificationPreference(Uuid::fromString(DemoUserUuids::forNumber(2)), 'invoice.issued', Channel::EMAIL, false);
        $this->em->persist($otherUser);
        $this->em->flush();

        $payload = ['preferences' => [
            ['type' => 'invoice.issued', 'channel' => 'email', 'enabled' => false],
            ['type' => 'payment.installment.overdue', 'channel' => 'in_app', 'enabled' => false],
        ]];

        $this->put('/api/v1/preferences', $payload);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = $this->json()['data'];
        self::assertFalse($data[0]['channels']['email']);
        self::assertTrue($data[0]['channels']['in_app']);
        self::assertFalse($data[1]['channels']['in_app']);
        self::assertTrue($data[1]['channels']['email']);

        // Repeating the same PUT is idempotent (one row per user/type/channel).
        $this->put('/api/v1/preferences', $payload);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(2, $this->em->getRepository(NotificationPreference::class)->count(['userId' => DemoUserUuids::forNumber(1)]));
        self::assertFalse($otherUser->isEnabled());
        self::assertSame(1, $this->em->getRepository(NotificationPreference::class)->count(['userId' => DemoUserUuids::forNumber(2)]));
    }

    public function testRejectsUnknownChannel(): void
    {
        $this->put('/api/v1/preferences', ['preferences' => [
            ['type' => 'invoice.issued', 'channel' => 'sms', 'enabled' => false],
        ]]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testMissingTokenReturns401(): void
    {
        $this->client->request('GET', '/api/v1/preferences');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('UNAUTHENTICATED', $this->json()['error']['code']);
    }

    private function get(string $url): void
    {
        $this->client->request('GET', $url, server: ['HTTP_X_AUTH_TOKEN' => 'valid-token']);
    }

    /** @param array<string, mixed> $payload */
    private function put(string $url, array $payload): void
    {
        $this->client->request(
            'PUT',
            $url,
            server: ['HTTP_X_AUTH_TOKEN' => 'valid-token', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload),
        );
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        return json_decode((string)$this->client->getResponse()->getContent(), true);
    }
}
