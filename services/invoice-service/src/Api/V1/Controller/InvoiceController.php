<?php

declare(strict_types=1);

namespace App\Api\V1\Controller;

use App\Api\V1\Documentation\ErrorDocument;
use App\Api\V1\Documentation\InvoiceDocument;
use App\Api\V1\Documentation\PaginationDocument;
use App\Api\V1\Mapper\InvoiceMapper;
use App\Api\V1\Request\ApplyPaymentRequest;
use App\Api\V1\Request\CreateInvoiceRequest;
use App\Api\V1\Request\IssueCreditNoteRequest;
use App\Api\V1\Request\IssueInvoiceRequest;
use App\Api\V1\Request\ListInvoicesQuery;
use App\Api\V1\Request\UpdateInvoiceRequest;
use App\Api\V1\Request\VoidInvoiceRequest;
use App\Exceptions\InvoiceNotFoundException;
use App\Handler\Invoice\ApplyPaymentHandler;
use App\Handler\Invoice\CreateDraftInvoiceHandler;
use App\Handler\Invoice\IssueCreditNoteHandler;
use App\Handler\Invoice\IssueInvoiceHandler;
use App\Handler\Invoice\UpdateDraftInvoiceHandler;
use App\Handler\Invoice\VoidInvoiceHandler;
use App\Repository\InvoiceRepository;
use App\Service\InvoicePdfRenderer;
use App\Utils\ApiResponse;
use App\Utils\Pagination;
use InvalidArgumentException;
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
use Symfony\Component\Uid\Uuid;

#[Route('/invoices', name: 'api_v1_invoice_')]
#[OA\Tag(name: 'Invoices')]
final class InvoiceController extends AbstractController
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly InvoiceMapper $mapper,
        private readonly CreateDraftInvoiceHandler $createDraftInvoice,
        private readonly UpdateDraftInvoiceHandler $updateDraftInvoice,
        private readonly IssueInvoiceHandler $issueInvoice,
        private readonly VoidInvoiceHandler $voidInvoice,
        private readonly IssueCreditNoteHandler $issueCreditNote,
        private readonly ApplyPaymentHandler $applyPayment,
        private readonly InvoicePdfRenderer $pdfRenderer,
    ) {}

    /**
     * List invoices.
     *
     * Returns a paginated list of invoices, optionally filtered by status,
     * type, order, party, free text, or overdue state.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of invoices.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: InvoiceDocument::class)),
                ),
                new OA\Property(property: 'pagination', ref: new Model(type: PaginationDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 400, description: 'Invalid query parameters.')]
    public function list(Request $request, #[MapQueryString(validationFailedStatusCode: 400)] ListInvoicesQuery $query = new ListInvoicesQuery()): JsonResponse
    {
        [$items, $total] = $this->invoices->search(
            $query->page,
            $query->pageSize,
            $query->status,
            $query->type,
            $query->orderId !== null ? Uuid::fromString($query->orderId) : null,
            $query->partyId !== null ? Uuid::fromString($query->partyId) : null,
            $query->search,
            $query->overdue,
        );

        $data = array_map($this->mapper->toResource(...), $items);
        $pagination = Pagination::create(
            $query->page,
            $query->pageSize,
            $total,
            fn(int $page): string => $this->generateUrl(
                'api_v1_invoice_list',
                [...$request->query->all(), 'page' => $page],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        );

        return new JsonResponse(ApiResponse::success($data, $pagination));
    }

    /** Show a single invoice by its UUID. */
    #[Route('/{id}', name: 'show', requirements: ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The requested invoice.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: InvoiceDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Invoice not found.')]
    public function show(string $id): JsonResponse
    {
        $invoice = $this->invoices->findByUuid($id) ?? throw new InvoiceNotFoundException($id);

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($invoice)));
    }

    /**
     * Create a draft invoice from an order snapshot.
     *
     * Tax is calculated by the Tax Service; the request never carries tax
     * values. A `invoice.created` outbox event is recorded in the same
     * transaction.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'Draft invoice created. The Location header points to the new resource.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: InvoiceDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 422, description: 'Validation failed.')]
    #[OA\Response(
        response: 409,
        description: 'The order already has an active invoice.',
        content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
    )]
    #[OA\Response(response: 503, description: 'The Tax Service is unavailable.')]
    public function create(#[MapRequestPayload] CreateInvoiceRequest $payload): JsonResponse
    {
        $invoice = ($this->createDraftInvoice)($payload->toInput());
        \assert($invoice !== null);

        return new JsonResponse(
            ApiResponse::success($this->mapper->toResource($invoice)),
            201,
            ['Location' => $this->generateUrl(
                'api_v1_invoice_show',
                ['id' => $invoice->getId()->toRfc4122()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            )],
        );
    }

    /**
     * Replace a draft invoice's fields and lines.
     *
     * Issued invoices are immutable; corrections go through credit notes.
     */
    #[Route('/{id}', name: 'update', requirements: ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['PUT'])]
    #[OA\Response(
        response: 200,
        description: 'Updated invoice. The Content-Location header points to it.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: InvoiceDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Invoice not found.')]
    #[OA\Response(response: 409, description: 'The invoice is no longer editable.')]
    #[OA\Response(response: 422, description: 'Validation failed.')]
    public function update(
        string $id,
        #[MapRequestPayload]
        UpdateInvoiceRequest $payload,
    ): JsonResponse {
        $invoice = ($this->updateDraftInvoice)($this->invoiceId($id), $payload->toInput());

        return new JsonResponse(
            ApiResponse::success($this->mapper->toResource($invoice)),
            200,
            ['Content-Location' => $this->generateUrl(
                'api_v1_invoice_show',
                ['id' => $invoice->getId()->toRfc4122()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            )],
        );
    }

    /**
     * Issue a draft invoice.
     *
     * Assigns the sequential invoice number and the due date. The due date
     * defaults to the configured payment term when the body omits it.
     */
    #[Route('/{id}/issue', name: 'issue', requirements: ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['POST'])]
    #[OA\Response(
        response: 200,
        description: 'Issued invoice.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: InvoiceDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Invoice not found.')]
    #[OA\Response(
        response: 409,
        description: 'The invoice is not a draft, or the due date is in the past.',
        content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
    )]
    #[OA\Response(response: 422, description: 'Validation failed.')]
    #[OA\Response(response: 503, description: 'Number generation failed.')]
    public function issue(
        string $id,
        #[MapRequestPayload]
        ?IssueInvoiceRequest $payload = null,
    ): JsonResponse {
        $invoice = ($this->issueInvoice)($this->invoiceId($id), $payload?->toDueDate());

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($invoice)));
    }

    /**
     * Void an invoice.
     *
     * Only drafts and issued invoices with no payment can be voided; a paid
     * invoice stays paid. Voiding frees the order for a new invoice.
     */
    #[Route('/{id}/void', name: 'void', requirements: ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['POST'])]
    #[OA\Response(
        response: 200,
        description: 'Voided invoice.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: InvoiceDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Invoice not found.')]
    #[OA\Response(
        response: 409,
        description: 'The invoice cannot be voided in its current state.',
        content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
    )]
    #[OA\Response(response: 422, description: 'Validation failed.')]
    public function void(
        string $id,
        #[MapRequestPayload]
        VoidInvoiceRequest $payload,
    ): JsonResponse {
        $invoice = ($this->voidInvoice)($this->invoiceId($id), $payload->reason);

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($invoice)));
    }

    /**
     * Issue a credit note against an invoice.
     *
     * Credit notes are the only way to correct an issued invoice. Their total
     * can never exceed the invoice value.
     */
    #[Route('/{id}/credit-notes', name: 'credit_note', requirements: ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['POST'])]
    #[OA\Response(
        response: 200,
        description: 'The invoice with its new credit note.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: InvoiceDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Invoice not found.')]
    #[OA\Response(
        response: 409,
        description: 'Credit notes would exceed the invoice value, or the invoice is draft/voided.',
        content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
    )]
    #[OA\Response(response: 422, description: 'Validation failed.')]
    public function creditNote(
        string $id,
        #[MapRequestPayload]
        IssueCreditNoteRequest $payload,
    ): JsonResponse {
        $invoice = ($this->issueCreditNote)($this->invoiceId($id), $payload->amount, $payload->reason);

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($invoice)));
    }

    /**
     * Apply a payment to an invoice.
     *
     * `paymentId` is the idempotency key: replaying it is a no-op. An amount
     * above the remaining balance is recorded but flagged, never applied.
     */
    #[Route('/{id}/payments', name: 'payment', requirements: ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['POST'])]
    #[OA\Response(
        response: 200,
        description: 'The invoice after the payment. overpaymentFlagged tells whether the amount was applied.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: new Model(type: InvoiceDocument::class)),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Invoice not found.')]
    #[OA\Response(
        response: 409,
        description: 'Draft/voided invoice, currency mismatch, or payment id used on another invoice.',
        content: new OA\JsonContent(ref: new Model(type: ErrorDocument::class)),
    )]
    #[OA\Response(response: 422, description: 'Validation failed.')]
    public function payment(
        string $id,
        #[MapRequestPayload]
        ApplyPaymentRequest $payload,
    ): JsonResponse {
        $invoiceId = $this->invoiceId($id);
        ($this->applyPayment)($invoiceId, $payload->toPaymentId(), $payload->amount, $payload->currency);

        $invoice = $this->invoices->findByUuid($invoiceId)
            ?? throw new InvoiceNotFoundException($invoiceId->toRfc4122());

        return new JsonResponse(ApiResponse::success($this->mapper->toResource($invoice)));
    }

    /**
     * Download the invoice as a PDF.
     *
     * Drafts render with a DRAFT watermark; issued invoices render their
     * number, lines, credit notes, and balance.
     */
    #[Route('/{id}/pdf', name: 'pdf', requirements: ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'], methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'The invoice PDF.',
        content: new OA\MediaType(mediaType: 'application/pdf', schema: new OA\Schema(type: 'string', format: 'binary')),
    )]
    #[OA\Response(response: 404, description: 'Invoice not found.')]
    public function pdf(string $id): Response
    {
        $invoice = $this->invoices->findByUuid($id) ?? throw new InvoiceNotFoundException($id);

        $filename = $invoice->getNumber() ?? $invoice->getId()->toRfc4122();

        return new Response(
            $this->pdfRenderer->render($invoice),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s.pdf"', $filename),
            ],
        );
    }

    private function invoiceId(string $id): Uuid
    {
        try {
            return Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            throw new InvoiceNotFoundException($id);
        }
    }
}
