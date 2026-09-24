<div>
    <x-alert />
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div><label for="report-search" class="block text-sm font-medium">Product, variant, SKU or barcode</label><input
                id="report-search" type="search" wire:model.live.debounce.300ms="search" maxlength="255"
                class="mt-1 w-full rounded-lg border border-border px-3 py-2"></div>
        <div><label for="report-warehouse"
                class="block text-sm font-medium">{{ $report === 'transfers' ? 'Source or destination warehouse' : 'Warehouse' }}</label><select
                id="report-warehouse" wire:model.live="warehouse"
                class="mt-1 w-full rounded-lg border border-border px-3 py-2">
                <option value="">All warehouses</option>
                @foreach ($warehouses as $location)
                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="report-category" class="block text-sm font-medium">Category</label><select id="report-category"
                wire:model.live="category" class="mt-1 w-full rounded-lg border border-border px-3 py-2">
                <option value="">All categories</option>
                @foreach ($categories as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select></div>
        @if (isset(\App\Services\ReportQuery::DATES[$report]))
            <div><label for="report-from" class="block text-sm font-medium">From date (inclusive)</label><input
                    id="report-from" type="date" wire:model.live="from"
                    class="mt-1 w-full rounded-lg border border-border px-3 py-2"></div>
            <div><label for="report-to" class="block text-sm font-medium">To date (inclusive)</label><input
                    id="report-to" type="date" wire:model.live="to"
                    class="mt-1 w-full rounded-lg border border-border px-3 py-2"></div>
        @endif
        @if ($report === 'expiration')
            <div><label for="report-period" class="block text-sm font-medium">Expiration window</label><select
                    id="report-period" wire:model.live="period"
                    class="mt-1 w-full rounded-lg border border-border px-3 py-2">
                    <option value="all">All positive-stock batches</option>
                    <option value="7">Within 7 days</option>
                    <option value="30">Within 30 days</option>
                    <option value="expired">Already expired</option>
                </select></div>
        @endif
    </div>
    <button wire:click="clearFilters" class="mb-4 text-sm underline">Clear filters</button>
    @if (in_array($report, ['inventory', 'valuation', 'low-stock']))
        <p class="mb-4 text-sm text-muted">Current snapshot, not a historical balance. Inventory and low-stock
            reports include zero balances for unstocked SKU/warehouse pairs, including inactive records. Low stock means
            on hand ≤ reorder level.</p>
    @endif
    @if ($report === 'valuation')
        <p class="mb-4 text-sm text-muted">Value = remaining batch quantity × batch unit cost, plus untracked
            quantity × current catalog cost. Includes reserved and expired stock. This is an operational valuation, not
            a historical accounting valuation.</p>
        @if ($valuation !== null)
            <p class="mb-4 text-lg font-semibold">
                {{-- Filtered inventory value: {{ $valuation }} --}}
                Filtered inventory value:
                <x-money :amount="$valuation" />
            </p>
        @endif
    @endif
    @if (in_array($report, ['purchases', 'adjustments', 'transfers']))
        <p class="mb-4 text-sm text-muted">One row per matching document line. Purchase dates use order date;
            adjustments use recorded date; transfers use transfer date. Draft/cancelled transfers are shown with their
            status and do not represent completed stock movement. Transfer warehouse filtering matches either endpoint.
        </p>
    @endif
    @if ($report === 'expiration')
        <p class="mb-4 text-sm text-muted">Only positive batch quantities are included. Upcoming windows include
            today and the final day. Expired means before today. Date filters intersect the selected window; choose
            “All” for a custom range. Nothing is automatically removed.</p>
    @endif
    @if ($rows)
        <div class="overflow-x-auto rounded-xl border border-border bg-surface">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b">
                        @foreach ($columns as $label)
                            <th class="whitespace-nowrap p-3">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr wire:key="report-row-{{ $rows->currentPage() }}-{{ $loop->index }}" class="border-b">
                            {{-- @foreach ($columns as $field => $label)
                                <td class="p-3">
                                    {{ in_array($field, \App\Services\ReportQuery::DECIMALS, true) ? \App\Services\ReportQuery::decimal($row->{$field}) : $row->{$field} ?? '—' }}
                                </td>
                            @endforeach --}}
                            @foreach ($columns as $field => $label)
                                <td class="p-3">
                                    @if (in_array($field, \App\Services\ReportQuery::MONEY, true))
                                        @if ($row->{$field} !== null)
                                            <x-money :amount="$row->{$field}" />
                                        @else
                                            —
                                        @endif
                                    @elseif (in_array($field, \App\Services\ReportQuery::DECIMALS, true))
                                        {{ \App\Services\ReportQuery::decimal($row->{$field}) }}
                                    @else
                                        {{ $row->{$field} ?? '—' }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty<tr>
                            <td colspan="{{ count($columns) }}" class="p-6 text-center text-slate-500">No records match
                                these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    @else
        <p class="rounded-xl bg-surface p-6">Correct the filter errors to view this report.</p>
    @endif
    <p wire:loading role="status">Updating report…</p>
</div>
