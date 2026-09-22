<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class PurchaseTotals
{
    /** Quantities and money are decimal strings. Each gross line rounds half-up to four places.
     * @param  array<int, array{stock_item_id: int|string, ordered_quantity: string|int, unit_cost: string|int, discount: string|int, tax: string|int}>  $items
     * @return array{items: array, subtotal: string, discount: string, tax: string, total: string}
     */
    public function calculate(array $items): array
    {
        $subtotal = $discount = $tax = BigDecimal::of('0.0000');
        $lines = [];
        foreach ($items as $index => $item) {
            $gross = BigDecimal::of((string) $item['ordered_quantity'])->multipliedBy((string) $item['unit_cost'])->toScale(4, RoundingMode::HalfUp);
            $lineDiscount = BigDecimal::of((string) $item['discount'])->toScale(4);
            $lineTax = BigDecimal::of((string) $item['tax'])->toScale(4);
            if ($lineDiscount->isGreaterThan($gross)) {
                throw ValidationException::withMessages(["items.$index.discount" => 'Discount cannot exceed the line subtotal.']);
            }
            $this->bounded($gross, "items.$index.unit_cost");
            $lines[] = [...$item, 'subtotal' => (string) $gross];
            $subtotal = $subtotal->plus($gross);
            $discount = $discount->plus($lineDiscount);
            $tax = $tax->plus($lineTax);
        }
        $total = $subtotal->minus($discount)->plus($tax);
        foreach (compact('subtotal', 'discount', 'tax', 'total') as $field => $value) {
            $this->bounded($value, $field);
        }

        return ['items' => $lines, 'subtotal' => (string) $subtotal, 'discount' => (string) $discount, 'tax' => (string) $tax, 'total' => (string) $total];
    }

    private function bounded(BigDecimal $value, string $field): void
    {
        if ($value->isNegative() || $value->isGreaterThan('9999999999999999.9999')) {
            throw ValidationException::withMessages([$field => 'The calculated amount exceeds the supported range.']);
        }
    }
}
