<?php

namespace App;

enum StockAdjustmentStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
