<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Services;

use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard KPI Service — fills the missing executive dashboard KPIs.
 *
 * Complements the existing dashboard with:
 * - Cash position (bank balances)
 * - AP aging (overdue vendor payables)
 * - Inventory health (turnover ratio, dead stock)
 * - Payroll cost trend
 */
final class DashboardKpiService implements ServiceContract
{
    /**
     * Get all supplementary KPIs.
     *
     * @return array{cash_position: array, ap_aging: array, inventory_health: array, payroll_trend: array}
     */
    public function supplementaryKpis(): array
    {
        return [
            'cash_position' => $this->cashPosition(),
            'ap_aging' => $this->apAging(),
            'inventory_health' => $this->inventoryHealth(),
            'payroll_trend' => $this->payrollTrend(),
        ];
    }

    private function cashPosition(): array
    {
        $balances = DB::table('bank_accounts')
            ->whereNull('deleted_at')
            ->select('bank_name', 'name as account_name', 'opening_balance as current_balance')
            ->get();

        $total = $balances->sum('current_balance');

        return [
            'total_balance' => round((float) $total, 2),
            'account_count' => $balances->count(),
            'accounts' => $balances->map(fn ($a) => [
                'bank' => $a->bank_name,
                'account' => $a->account_name,
                'balance' => round((float) $a->current_balance, 2),
            ])->toArray(),
        ];
    }

    private function apAging(): array
    {
        $today = now()->toDateString();

        $overdue = DB::table('vendor_invoices')
            ->whereIn('status', ['approved', 'partially_paid'])
            ->where('due_date', '<', $today)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(net_amount + vat_amount - ewt_amount), 0) as total')
            ->first();

        $current = DB::table('vendor_invoices')
            ->whereIn('status', ['approved', 'partially_paid'])
            ->where('due_date', '>=', $today)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(net_amount + vat_amount - ewt_amount), 0) as total')
            ->first();

        return [
            'overdue_count' => (int) ($overdue->count ?? 0),
            'overdue_total' => round((float) ($overdue->total ?? 0), 2),
            'current_count' => (int) ($current->count ?? 0),
            'current_total' => round((float) ($current->total ?? 0), 2),
        ];
    }

    private function inventoryHealth(): array
    {
        // Total inventory value
        $totalValue = DB::table('stock_balances')
            ->join('item_masters', 'stock_balances.item_id', '=', 'item_masters.id')
            ->selectRaw('COALESCE(SUM(CAST(stock_balances.quantity_on_hand AS numeric) * COALESCE(item_masters.standard_price_centavos, 0) / 100.0), 0) as total_value')
            ->value('total_value');

        // Dead stock: items with no movement in 90+ days
        $deadStockCount = DB::table('item_masters')
            ->whereNotExists(function ($q): void {
                $q->select(DB::raw(1))
                    ->from('stock_ledger')
                    ->whereColumn('stock_ledger.item_id', 'item_masters.id')
                    ->where('stock_ledger.created_at', '>=', now()->subDays(90));
            })
            ->where('item_masters.is_active', true)
            ->whereNull('item_masters.deleted_at')
            ->count();

        // Low stock count
        $lowStockCount = DB::table('stock_balances')
            ->join('item_masters', 'stock_balances.item_id', '=', 'item_masters.id')
            ->whereRaw('CAST(stock_balances.quantity_on_hand AS numeric) <= CAST(item_masters.reorder_point AS numeric)')
            ->where('item_masters.is_active', true)
            ->count();

        return [
            'total_value' => round((float) $totalValue, 2),
            'dead_stock_items' => $deadStockCount,
            'low_stock_items' => $lowStockCount,
        ];
    }

    private function payrollTrend(): array
    {
        $months = DB::table('payroll_runs')
            ->where('status', 'completed')
            ->where('pay_date', '>=', now()->subMonths(6)->startOfMonth())
            ->whereNull('deleted_at')
            ->selectRaw("
                TO_CHAR(pay_date, 'YYYY-MM') as month,
                COALESCE(SUM(gross_pay_total_centavos), 0) / 100 as total_gross,
                COALESCE(SUM(net_pay_total_centavos), 0) / 100 as total_net,
                COUNT(*) as run_count
            ")
            ->groupByRaw("TO_CHAR(pay_date, 'YYYY-MM')")
            ->orderBy('month')
            ->get();

        return $months->map(fn ($m) => [
            'month' => $m->month,
            'total_gross' => round((float) $m->total_gross, 2),
            'total_net' => round((float) $m->total_net, 2),
            'run_count' => (int) $m->run_count,
        ])->toArray();
    }
}
