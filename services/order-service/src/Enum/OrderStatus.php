<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Order status states following the state machine in behavioral diagrams.
 * State transitions:
 * - DRAFT -> SUBMITTED (requires at least one order line)
 * - SUBMITTED -> RECEIVED (can repeat for multiple deliveries)
 * - RECEIVED -> PARTIALLY_RECEIVED (partial delivery before full)
 * - PARTIALLY_RECEIVED -> RECEIVED (additional delivery)
 * - * -> CANCELED (any state can be canceled)
 * - RECEIVED/DELIVERED -> SHIPPED (for sales orders)
 * - SHIPPED -> DELIVERED
 */
enum OrderStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case RECEIVED = 'received';
    case PARTIALLY_RECEIVED = 'partially_received';
    case CANCELED = 'canceled';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case STOCK_REJECTED = 'stock_rejected';
}