<?php

namespace App\Tests\Unit\Controller\Api\V1;

use App\Api\V1\Controller\InventoryController;
use App\Api\V1\Mapper\InventoryMapper;
use App\Api\V1\Request\UpdateInventoryRequest;
use App\Entity\Inventory;
use App\Repository\InventoryMovementRepository;
use App\Repository\InventoryRepository;
use App\Service\StockMovementService;
use App\Tests\Support\ProductUuidTrait;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class InventoryControllerTest extends TestCase
{
    use ProductUuidTrait;

    #[AllowMockObjectsWithoutExpectations]
    public function testUpdateReturns409WhenFlushThrowsOptimisticLockException(): void
    {
        $inventory = new Inventory()
            ->setProductId(Uuid::fromString($this->productUuid(42)))
            ->setQuantityOnHand('100.000')
            ->setQuantityReserved('10.000')
            ->setReorderLevel('20.000')
            ->setCreatedAt(new DateTimeImmutable())
            ->setUpdatedAt(new DateTimeImmutable());

        $id = $this->productUuid(7);
        $repository = $this->createMock(InventoryRepository::class);
        $repository->expects(self::once())
            ->method('findActive')
            ->with($id)
            ->willReturn($inventory);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())
            ->method('lock')
            ->with($inventory, LockMode::OPTIMISTIC, 1);
        $em->expects(self::once())
            ->method('flush')
            ->willThrowException(OptimisticLockException::lockFailed($inventory));

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects(self::never())->method('invalidateTags');

        $movements = self::createStub(InventoryMovementRepository::class);
        $stock = new StockMovementService($em, $repository, $movements);
        $mapper = new InventoryMapper();
        $controller = new InventoryController($cache, $repository, $movements, $stock, $mapper);
        $container = new Container();
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')->willReturnCallback(
            static fn(mixed $data, string $format, array $context = []): string => json_encode($data),
        );
        $container->set('serializer', $serializer);
        $controller->setContainer($container);

        $response = $controller->update(
            $id,
            new UpdateInventoryRequest(reorderLevel: '5', version: 1),
        );

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertSame(
            '{"error":"The inventory item was modified by another request."}',
            $response->getContent(),
        );
    }
}
