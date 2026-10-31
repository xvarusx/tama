<?php

namespace App\Controller\Api;

use App\Dto\ContactFilterInput;
use App\Dto\CreateContactInput;
use App\Entity\Contact;
use App\Entity\ContactAuditEntry;
use App\Security\ContactUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api/contact')]
final class ContactController extends AbstractController
{
    public function __construct(
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    #[Route('', name: 'app_api_contact_index', methods: ['GET'])]
    public function index(
        SerializerInterface $serializer,
        Request $request,
        EntityManagerInterface $em,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ?ContactFilterInput $filterInput = null,
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 10)));
        $filter = $filterInput ?? new ContactFilterInput();

        $key = sprintf(
            'contact_%s_%s_%s_page_%d_limit_%d',
            $filter->contactType?->value ?? 'all',
            $filter->contactStatus?->value ?? 'all',
            $filter->trustLevel?->value ?? 'all',
            $page,
            $limit,
        );

        $json = $this->cache->get(
            $key,
            function (ItemInterface $item) use ($serializer, $page, $limit, $em, $filter): string {
                $item->expiresAfter(300);
                $item->tag(['contact']);

                $criteria = array_filter(
                    [
                        'contactType' => $filter->contactType,
                        'status' => $filter->contactStatus,
                        'trustLevel' => $filter->trustLevel,
                    ],
                    static fn (mixed $value): bool => null !== $value,
                );

                $contacts = $em->getRepository(Contact::class)->findBy(
                    $criteria,
                    ['name' => 'ASC'],
                    $limit,
                    ($page - 1) * $limit,
                );

                return $serializer->serialize($contacts, 'json');
            }
        );

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'app_api_contact_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function showById(Contact $contact, SerializerInterface $serializer): JsonResponse
    {
        return new JsonResponse($serializer->serialize($contact, 'json'), Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'app_api_contact_create', methods: ['POST'])]
    public function create(
        EntityManagerInterface $em,
        #[MapRequestPayload] CreateContactInput $input,
        SerializerInterface $serializer,
    ): JsonResponse {
        $userId = $this->currentUserId();
        $contact = new Contact($input->name, $userId, $input->contactType);
        $contact->setEmail($input->email);
        $contact->setPhone($input->phone);
        $contact->setAddress($input->address);
        $contact->setTaxNumber($input->taxNumber);

        $em->persist($contact);
        $em->persist(new ContactAuditEntry($contact->getId(), $userId, 'created'));
        $em->flush();
        $this->cache->invalidateTags(['contact']);

        return new JsonResponse(
            $serializer->serialize($contact, 'json'),
            Response::HTTP_CREATED,
            [],
            true,
        );
    }

    #[Route('/{id}', name: 'app_api_contact_update', requirements: ['id' => Requirement::UUID], methods: ['PUT', 'PATCH'])]
    public function update(
        EntityManagerInterface $em,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        Contact $contact,
    ): JsonResponse {
        try {
            $serializer->deserialize(
                $request->getContent(),
                Contact::class,
                'json',
                ['object_to_populate' => $contact],
            );
        } catch (NotEncodableValueException $e) {
            throw new BadRequestHttpException('Request body must be valid JSON.', $e);
        }

        $violations = $validator->validate($contact);
        if (count($violations) > 0) {
            throw new UnprocessableEntityHttpException((string) $violations);
        }

        $userId = $this->currentUserId();
        $contact->markUpdatedBy($userId);
        $em->persist(new ContactAuditEntry($contact->getId(), $userId, 'updated'));
        $em->flush();
        $this->cache->invalidateTags(['contact']);

        return new JsonResponse(
            $serializer->serialize($contact, 'json'),
            Response::HTTP_OK,
            [],
            true,
        );
    }

    #[Route('/{id}', name: 'app_api_contact_delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    public function delete(EntityManagerInterface $em, Contact $contact): JsonResponse
    {
        $userId = $this->currentUserId();
        $em->persist(new ContactAuditEntry($contact->getId(), $userId, 'deleted'));
        $em->remove($contact);
        $em->flush();

        $this->cache->invalidateTags(['contact']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
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
