<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Entity\Setting;
use App\Security\Caller;
use App\Tests\Support\FakeAuthServiceClient;
use App\Utils\DemoUserUuids;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class AdminSettingsControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $container = $this->client->getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConfiguration()->setUseSchemaEditorApi(true);
        new SchemaTool($this->em)->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $auth = $container->get(FakeAuthServiceClient::class);
        $auth->setCaller(new Caller(DemoUserUuids::forNumber(1), 'admin@example.com', 'Admin', ['ROLE_USER', 'ROLE_ADMIN']));

        $this->em->persist(new Setting('notifications.retention_days', '30', 'Days to keep archived notifications'));
        $this->em->persist(new Setting('notifications.max_attempts', '3', 'Maximum send attempts'));
        $this->em->flush();
    }

    public function testUpdateSettingValidatesNumericKeys(): void
    {
        $this->request('PUT', '/api/v1/admin/settings/notifications.retention_days', ['value' => '14']);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('14', $this->json()['data']['value']);
        self::assertSame('14', $this->em->find(Setting::class, $this->settingId('notifications.retention_days'))->getValue());

        $this->request('PUT', '/api/v1/admin/settings/notifications.max_attempts', ['value' => '0']);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->request('PUT', '/api/v1/admin/settings/notifications.max_attempts', ['value' => 'not-a-number']);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->request('PUT', '/api/v1/admin/settings/unknown.key', ['value' => '1']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testListsAllSettings(): void
    {
        $this->request('GET', '/api/v1/admin/settings');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = $this->json()['data'];
        self::assertCount(2, $data);
        self::assertSame('notifications.retention_days', $data[0]['key']);
        self::assertSame('30', $data[0]['value']);
        self::assertArrayHasKey('description', $data[0]);
    }

    private function settingId(string $key): Uuid
    {
        return $this->em->getRepository(Setting::class)->findOneBy(['key' => $key])->getId();
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
