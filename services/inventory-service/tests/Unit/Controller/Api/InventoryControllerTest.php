<?php

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\InventoryController;
use App\Dto\UpdateInventoryInput;
use App\Entity\Inventory;
use App\Repository\InventoryMovementRepository;
use App\Repository\InventoryRepository;
use App\Service\StockMovementService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class InventoryControllerTest extends TestCase
{
    public function testUpdateReturns409WhenFlushThrowsOptimisticLockException(): void
    {
        $inventory = (new Inventory())
            ->setProductId(42)
            ->setQuantityOnHand('100.000')
            ->setQuantityReserved('10.000')
            ->setReorderLevel('20.000')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $repository = $this->createMock(InventoryRepository::class);
        $repository->expects(self::once())
            ->method('findActive')
            ->with(7)
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

        $movements = $this->createStub(InventoryMovementRepository::class);
        $stock = new StockMovementService($em, $repository, $movements);
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('serialize')->willReturn('{"error":"The inventory item was modified by another request."}');
        $controller = new InventoryController($cache, $repository, $movements, $stock, $serializer);
        $container = new Container();
        $container->set('serializer', $serializer);
        $controller->setContainer($container);

        $response = $controller->update(
            7,
            new UpdateInventoryInput(reorderLevel: '5', version: 1),
        );

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertSame(
            '{"error":"The inventory item was modified by another request."}',
            $response->getContent(),
        );
    }
}
