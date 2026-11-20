<?php

declare(strict_types=1);

namespace App\Handler\Delivery;

use App\Entity\Delivery;
use App\Exceptions\DeliveryNotFound;
use App\Repository\DeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/** Administrator action: move a failed delivery back into the worker queue. */
final readonly class RequeueDeliveryHandler
{
    public function __construct(
        private DeliveryRepository $deliveries,
        private EntityManagerInterface $em,
    ) {}

    /** @throws DeliveryNotFound when the id does not exist */
    public function __invoke(Uuid $id): Delivery
    {
        $delivery = $this->deliveries->findWithAttempts($id) ?? throw new DeliveryNotFound($id);
        $delivery->requeue(); // throws InvalidDeliveryTransition unless FAILED
        $this->em->flush();

        return $delivery;
    }
}
