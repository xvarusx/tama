<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\ErrorDocument;
use App\Api\V1\Documentation\PaymentDocument;
use App\Api\V1\Documentation\PaginationDocument;
use App\Api\V1\Mapper\PaymentMapper;
use App\Api\V1\Request\CreatePaymentRequest;
use App\Api\V1\Request\ListPaymentsQuery;
use App\Api\V1\Request\ReversePaymentRequest;
use App\Api\V1\Resource\PaymentResource;
use App\Entity\OutboxEvent;
use App\Handler\Payment\CancelPaymentHandler;
use App\Handler\Payment\CreatePaymentHandler;
use App\Handler\Payment\FailPaymentHandler;
use App\Handler\Payment\ReversePaymentHandler;
use App\Handler\Payment\RecordPaymentHandler;
use App\Repository\PaymentRepository;
use App\Utils\ApiResponse;
use App\Utils\Pagination;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/payments', name: 'api_v1_payment_')]
#[OA\Tag(name: 'Payments')]
final class PaymentsController extends AbstractController
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly PaymentMapper $mapper,
        private readonly CreatePaymentHandler $createPayment,
        private readonly RecordPaymentHandler $recordPayment,
        private readonly CancelPaymentHandler $cancelPayment,
        private readonly FailPaymentHandler $failPayment,
        private readonly ReversePaymentHandler $reversePayment,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of payments.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: PaymentDocument::class)),
                ),
                new OA\Property(property: 'pagination', ref: new Model(type: PaginationDocument::class)),
            ],
            type: 'object',
        ),
    )]
    public function list(Request $request, #[MapQueryString] ListPaymentsQuery $query): JsonResponse
    {
        [$items, $total] = $this->payments->search(
            $query->page,
            $query->pageSize,
            $query->status,
            $query->method,
            $query->partyId,
            $query->search,
        );
        
        $data = array_map($this->mapper->toResource(...), $items);
        
        $pagination = Pagination::create(
            $query->page,
            $query->pageSize,
            $total,
            fn(int $page): string => $this->generateUrl(
                'api_v1_payment_list',
                [...$request->query->all(), 'page' => $page],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        );

        return new JsonResponse(ApiResponse::success($data, $pagination));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The requested payment.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: PaymentDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Payment not found.')]
    public function show(string $id): JsonResponse
    {
        $payment = $this->payments->find($id) 
            ?? throw $this->createNotFoundException('Payment not found.');

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($payment)));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'Payment created. The Location header points to the new resource.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: PaymentDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 422, description: 'Validation failed.')]
    public function create(#[MapRequestPayload] CreatePaymentRequest $payload): JsonResponse
    {
        $payment = ($this->createPayment)($payload);

        $response = new JsonResponse(
            ApiResponse::success($this->mapper->toResource($payment)),
            Response::HTTP_CREATED,
        );

        $response->headers->set('Location', $this->generateUrl(
            'api_v1_payment_show',
            ['id' => $payment->getId()->toRfc4122()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));

        return $response;
    }

    #[Route('/{id}/record', name: 'record', methods: ['POST'])]
    #[OA\Response(
        response: 200,
        description: 'Payment recorded (cheque cleared).',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: PaymentDocument::class)),
            ],
            type: 'object',
        ),
    )]
    public function record(string $id): JsonResponse
    {
        $payment = $this->payments->find($id) ?? throw $this->createNotFoundException('Payment not found.');
        
        if ($payment->getStatus() !== \App\Enum\PaymentStatus::PENDING) {
            return new JsonResponse(
                ApiResponse::error('INVALID_STATE', 'Only a PENDING payment can be recorded.'),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $payment->confirm();
        
        $this->em->wrapInTransaction(function () use ($payment): void {
            $this->em->persist($payment);
            $this->em->flush();
            $this->em->persist(\App\Event\PaymentCompletedV1::toOutbox($payment));
            $this->em->flush();
        });

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($payment)));
    }

    #[Route('/{id}/fail', name: 'fail', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Payment failed.')]
    public function fail(string $id): JsonResponse
    {
        $payment = $this->payments->find($id) ?? throw $this->createNotFoundException('Payment not found.');
        
        if ($payment->getStatus() !== \App\Enum\PaymentStatus::PENDING) {
            return new JsonResponse(
                ApiResponse::error('INVALID_STATE', 'Only a PENDING payment can be failed.'),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $payment->fail('Payment failed');
        
        $this->em->wrapInTransaction(function () use ($payment): void {
            $this->em->persist($payment);
            $this->em->flush();
            $this->em->persist(\App\Event\PaymentFailedV1::toOutbox($payment, 'Payment failed'));
            $this->em->flush();
        });

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($payment)));
    }

    #[Route('/{id}/reverse', name: 'reverse', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Payment reversed.')]
    public function reverse(string $id): JsonResponse
    {
        $payment = $this->payments->find($id) ?? throw $this->createNotFoundException('Payment not found.');
        
        if ($payment->getStatus() !== \App\Enum\PaymentStatus::COMPLETED) {
            return new JsonResponse(
                ApiResponse::error('INVALID_STATE', 'Only a COMPLETED payment can be reversed.'),
                Response::HTTP_BAD_REQUEST,
            );
        }

        $payment->reverse('Reversal requested');
        
        $this->em->wrapInTransaction(function () use ($payment): void {
            $this->em->persist($payment);
            $this->em->flush();
            $this->em->persist(\App\Event\PaymentReversedV1::toOutbox($payment, 'Reversal requested'));
            $this->em->flush();
        });

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($payment)));
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Payment canceled.')]
    public function cancel(string $id): JsonResponse
    {
        $payment = $this->payments->find($id) ?? throw $this->createNotFoundException('Payment not found.');

        $payment->cancel();
        
        $this->em->wrapInTransaction(function () use ($payment): void {
            $this->em->persist($payment);
            $this->em->flush();
            $this->em->persist(\App\Event\PaymentCanceledV1::toOutbox($payment));
            $this->em->flush();
        });

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($payment)));
    }
}