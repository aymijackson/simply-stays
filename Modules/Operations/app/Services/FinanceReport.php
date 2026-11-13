<?php

namespace Modules\Operations\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Listings\Models\Property;
use Modules\Operations\Models\FinanceTransaction;

/** Income-and-expenditure figures for one property over a date range. */
class FinanceReport
{
    /**
     * @return array{income: int, expense: int, net: int, income_by_category: Collection, expense_by_category: Collection}
     */
    public static function summary(Property $property, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = FinanceTransaction::query()
            ->join('ops_finance_categories as c', 'c.id', '=', 'ops_finance_transactions.category_id')
            ->where('ops_finance_transactions.property_id', $property->id)
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->groupBy('ops_finance_transactions.type', 'c.name')
            ->selectRaw('ops_finance_transactions.type as type, c.name as category, SUM(amount) as total, COUNT(*) as entries')
            ->get();

        $income = $rows->where('type', 'income')->sortByDesc('total')->values();
        $expense = $rows->where('type', 'expense')->sortByDesc('total')->values();
        $incomeTotal = (int) $income->sum('total');
        $expenseTotal = (int) $expense->sum('total');

        return [
            'income' => $incomeTotal,
            'expense' => $expenseTotal,
            'net' => $incomeTotal - $expenseTotal,
            'income_by_category' => $income,
            'expense_by_category' => $expense,
        ];
    }

    /**
     * Income and expense per calendar month, oldest first.
     *
     * @return Collection<int, array{month: CarbonImmutable, income: int, expense: int, net: int}>
     */
    public static function monthly(Property $property, int $months = 6, ?CarbonImmutable $endingIn = null): Collection
    {
        $end = ($endingIn ?? CarbonImmutable::today())->endOfMonth();
        $start = $end->subMonthsNoOverflow($months - 1)->startOfMonth();

        $transactions = FinanceTransaction::query()
            ->where('property_id', $property->id)
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->get(['type', 'amount', 'occurred_on']);

        return collect(range(0, $months - 1))->map(function (int $offset) use ($start, $transactions) {
            $month = $start->addMonthsNoOverflow($offset);
            $inMonth = $transactions->filter(fn ($t) => $t->occurred_on->format('Y-m') === $month->format('Y-m'));
            $income = (int) $inMonth->where('type', 'income')->sum('amount');
            $expense = (int) $inMonth->where('type', 'expense')->sum('amount');

            return ['month' => $month, 'income' => $income, 'expense' => $expense, 'net' => $income - $expense];
        });
    }
}
