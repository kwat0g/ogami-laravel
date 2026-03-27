<?php

declare(strict_types=1);

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\ClientOrder;
use App\Domains\CRM\Models\Ticket;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales Analytics Service — pipeline funnel, revenue analysis, customer insights.
 */
final class SalesAnalyticsService implements ServiceContract
{
    /**
     * Sales pipeline funnel — count and value of orders at each stage.
     *
     * @return Collection<int, array{status: string, count: int, total_value_centavos: int}>
     */
    public function pipelineFunnel(): Collection
    {
        return ClientOrder::query()
            ->select(
                'status',
                DB::raw('count(*) as order_count'),
                DB::raw('coalesce(sum(total_amount_centavos), 0) as total_value_centavos'),
            )
            ->groupBy('status')
            ->orderByRaw("CASE status
                WHEN 'pending' THEN 1
                WHEN 'negotiating' THEN 2
                WHEN 'client_responded' THEN 3
                WHEN 'approved' THEN 4
                WHEN 'rejected' THEN 5
                WHEN 'cancelled' THEN 6
                ELSE 7 END")
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->order_count,
                'total_value_centavos' => (int) $row->total_value_centavos,
            ]);
    }

    /**
     * Revenue by customer — top customers by approved order value.
     *
     * @return Collection<int, array{customer_id: int, customer_name: string, order_count: int, total_revenue_centavos: int}>
     */
    public function revenueByCustomer(?int $year = null, int $limit = 20): Collection
    {
        return ClientOrder::query()
            ->join('customers', 'customers.id', '=', 'client_orders.customer_id')
            ->where('client_orders.status', 'approved')
            ->when($year, fn ($q, $y) => $q->whereYear('client_orders.created_at', $y))
            ->select(
                'client_orders.customer_id',
                'customers.name as customer_name',
                DB::raw('count(*) as order_count'),
                DB::raw('coalesce(sum(client_orders.total_amount_centavos), 0) as total_revenue_centavos'),
            )
            ->groupBy('client_orders.customer_id', 'customers.name')
            ->orderByDesc('total_revenue_centavos')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'customer_id' => (int) $row->customer_id,
                'customer_name' => $row->customer_name,
                'order_count' => (int) $row->order_count,
                'total_revenue_centavos' => (int) $row->total_revenue_centavos,
            ]);
    }

    /**
     * Revenue by month (trend chart data).
     *
     * @return Collection<int, array{month: string, order_count: int, total_revenue_centavos: int}>
     */
    public function monthlyRevenueTrend(int $year): Collection
    {
        return ClientOrder::query()
            ->where('status', 'approved')
            ->whereYear('created_at', $year)
            ->select(
                DB::raw("to_char(created_at, 'YYYY-MM') as month"),
                DB::raw('count(*) as order_count'),
                DB::raw('coalesce(sum(total_amount_centavos), 0) as total_revenue_centavos'),
            )
            ->groupBy(DB::raw("to_char(created_at, 'YYYY-MM')"))
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'order_count' => (int) $row->order_count,
                'total_revenue_centavos' => (int) $row->total_revenue_centavos,
            ]);
    }

    /**
     * Win rate — approved orders / (approved + rejected + cancelled).
     *
     * @return array{total_decided: int, won: int, lost: int, win_rate_pct: float, avg_negotiation_rounds: float}
     */
    public function winRate(?int $year = null): array
    {
        $query = ClientOrder::query()
            ->whereIn('status', ['approved', 'rejected', 'cancelled'])
            ->when($year, fn ($q, $y) => $q->whereYear('created_at', $y));

        $won = (clone $query)->where('status', 'approved')->count();
        $lost = (clone $query)->whereIn('status', ['rejected', 'cancelled'])->count();
        $total = $won + $lost;

        $avgRounds = ClientOrder::query()
            ->whereIn('status', ['approved', 'rejected', 'cancelled'])
            ->when($year, fn ($q, $y) => $q->whereYear('created_at', $y))
            ->avg('negotiation_round') ?? 0;

        return [
            'total_decided' => $total,
            'won' => $won,
            'lost' => $lost,
            'win_rate_pct' => $total > 0 ? round(($won / $total) * 100, 1) : 0.0,
            'avg_negotiation_rounds' => round((float) $avgRounds, 1),
        ];
    }

    /**
     * Support ticket statistics.
     *
     * @return array{total_open: int, total_resolved: int, avg_resolution_hours: float, overdue_count: int}
     */
    public function ticketStats(): array
    {
        $open = Ticket::whereIn('status', ['open', 'in_progress'])->count();
        $resolved = Ticket::whereIn('status', ['resolved', 'closed'])->count();

        $avgHours = Ticket::query()
            ->whereIn('status', ['resolved', 'closed'])
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 3600) as avg_hours')
            ->value('avg_hours');

        $overdue = Ticket::query()
            ->whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('sla_deadline')
            ->where('sla_deadline', '<', now())
            ->count();

        return [
            'total_open' => $open,
            'total_resolved' => $resolved,
            'avg_resolution_hours' => round((float) ($avgHours ?? 0), 1),
            'overdue_count' => $overdue,
        ];
    }
}
