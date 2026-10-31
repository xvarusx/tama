<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\BlacklistContactInput;
use App\Dto\ChangeTrustLevelInput;
use App\Entity\Contact;
use App\Entity\ContactAuditEntry;
use App\Security\ContactUser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * State changes of a contact (client or supplier): blacklist, deactivate, trust level.
 * The business rules live in the Contact entity; this controller only wires HTTP to it.
 */
#[Route('/api/contact/{id}', requirements: ['id' => Requirement::UUID])]
final class ContactStatusController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TagAwareCacheInterface $cache,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('/blacklist', name: 'app_api_contact_blacklist', methods: ['POST'])]
    public function blacklist(Contact $contact, #[MapRequestPayload] BlacklistContactInput $input): JsonResponse
    {
        $userId = $this->currentUserId();

        return $this->apply($contact, $userId, 'blacklisted', fn () => $contact->blacklist($input->reason, $userId));
    }

    #[Route('/reinstate', name: 'app_api_contact_reinstate', methods: ['POST'])]
    public function reinstate(Contact $contact): JsonResponse
    {
        $userId = $this->currentUserId();

        return $this->apply($contact, $userId, 'reinstated', fn () => $contact->reinstate($userId));
    }

    #[Route('/deactivate', name: 'app_api_contact_deactivate', methods: ['POST'])]
    public function deactivate(Contact $contact): JsonResponse
    {
        $userId = $this->currentUserId();

        return $this->apply($contact, $userId, 'deactivated', fn () => $contact->deactivate($userId));
    }

    #[Route('/activate', name: 'app_api_contact_activate', methods: ['POST'])]
    public function activate(Contact $contact): JsonResponse
    {
        $userId = $this->currentUserId();

        return $this->apply($contact, $userId, 'activated', fn () => $contact->activate($userId));
    }

    #[Route('/trust-level', name: 'app_api_contact_trust_level', methods: ['PATCH'])]
    public function changeTrustLevel(Contact $contact, #[MapRequestPayload] ChangeTrustLevelInput $input): JsonResponse
    {
        $userId = $this->currentUserId();

        return $this->apply($contact, $userId, 'trust_level_changed', fn () => $contact->changeTrustLevel($input->trustLevel, $userId));
    }

    /**
     * Runs a domain action, saves it, clears the list cache and publishes the domain events.
     */
    private function apply(Contact $contact, int $userId, string $change, \Closure $action): JsonResponse
    {
        try {
            $action();
            $this->em->persist(new ContactAuditEntry($contact->getId(), $userId, $change));
            $this->em->flush();
        } catch (\DomainException $e) {
            // A rule of the entity was broken (e.g. reinstating a contact that is not blacklisted).
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (OptimisticLockException $e) {
            throw new ConflictHttpException('The contact was changed by someone else, reload it and try again.', $e);
        }

        // The filtered list (GET /api/contact) is cached by tag, so it must be cleared after a change.
        $this->cache->invalidateTags(['contact']);

        foreach ($contact->pullEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $this->json($contact);
    }

    private function currentUserId(): int
    {
        $user = $this->getUser();
        if (!$user instanceof ContactUser) {
            throw $this->createAccessDeniedException('Authentication is required.');
        }

        return $user->getId();
    }
}
