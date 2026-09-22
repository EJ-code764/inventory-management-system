<?php

namespace App;

enum StockTransferStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
