<?php

namespace App\Enum;

enum InventoryMovementType: string
{
    case InitialStock = 'initial_stock';
    case PurchaseReceipt = 'purchase_receipt';
    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Receipt = 'receipt';
    case WriteOff = 'write_off';
    case Reserve = 'reserve';
    case Release = 'release';
}
