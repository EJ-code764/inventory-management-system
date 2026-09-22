<?php

namespace App;

enum InventoryMovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case PurchaseReturn = 'purchase_return';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Damage = 'damage';
    case Expired = 'expired';
}
