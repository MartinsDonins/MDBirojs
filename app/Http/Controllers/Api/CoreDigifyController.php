<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoreDigifyController extends Controller
{
    /** VID columns that qualify as business income */
    private const VID_COLS = [4, 5, 6];

    /**
     * Search for income transactions matching given criteria.
     *
     * POST /api/coredigify/transactions/search
     *
     * Body (all optional):
     *   amount          – exact EUR amount
     *   amount_from     – minimum EUR amount
     *   amount_to       – maximum EUR amount
     *   date_from       – YYYY-MM-DD start date (occurred_at)
     *   date_to         – YYYY-MM-DD end date (occurred_at)
     *   counterparty    – partial match on counterparty_name
     *   reference       – partial match on reference
     *   description     – partial match on description
     *   limit           – max results (default 20, max 100)
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'amount'       => 'nullable|numeric|min:0',
            'amount_from'  => 'nullable|numeric|min:0',
            'amount_to'    => 'nullable|numeric|min:0',
            'date_from'    => 'nullable|date_format:Y-m-d',
            'date_to'      => 'nullable|date_format:Y-m-d',
            'counterparty' => 'nullable|string|max:255',
            'reference'    => 'nullable|string|max:255',
            'description'  => 'nullable|string|max:500',
            'limit'        => 'nullable|integer|min:1|max:100',
        ]);

        $query = Transaction::with(['account', 'category', 'cashOrder'])
            ->where('type', 'INCOME')
            ->where('status', 'COMPLETED')
            ->whereHas('category', fn ($q) => $q->whereIn('vid_column', self::VID_COLS));

        if ($request->filled('amount')) {
            $query->where('amount_eur', $request->float('amount'));
        }
        if ($request->filled('amount_from')) {
            $query->where('amount_eur', '>=', $request->float('amount_from'));
        }
        if ($request->filled('amount_to')) {
            $query->where('amount_eur', '<=', $request->float('amount_to'));
        }
        if ($request->filled('date_from')) {
            $query->where('occurred_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('occurred_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('counterparty')) {
            $query->where('counterparty_name', 'ilike', '%' . $request->input('counterparty') . '%');
        }
        if ($request->filled('reference')) {
            $query->where('reference', 'ilike', '%' . $request->input('reference') . '%');
        }
        if ($request->filled('description')) {
            $query->where('description', 'ilike', '%' . $request->input('description') . '%');
        }

        $limit        = min((int) ($request->input('limit', 20)), 100);
        $transactions = $query->orderByDesc('occurred_at')->limit($limit)->get();

        return response()->json([
            'count' => $transactions->count(),
            'data'  => $transactions->map(fn ($tx) => $this->formatTransaction($tx)),
        ]);
    }

    /**
     * Fetch a specific transaction by ID.
     *
     * GET /api/coredigify/transactions/{id}
     */
    public function show(int $id): JsonResponse
    {
        $transaction = Transaction::with(['account', 'category', 'cashOrder'])
            ->where('type', 'INCOME')
            ->where('status', 'COMPLETED')
            ->whereHas('category', fn ($q) => $q->whereIn('vid_column', self::VID_COLS))
            ->find($id);

        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        return response()->json($this->formatTransaction($transaction));
    }

    private function formatTransaction(Transaction $tx): array
    {
        return [
            'transaction_id'       => $tx->id,
            'occurred_at'          => $tx->occurred_at?->toDateString(),
            'booked_at'            => $tx->booked_at?->toDateString(),
            'amount'               => (float) $tx->amount,
            'amount_eur'           => (float) $tx->amount_eur,
            'currency'             => $tx->currency,
            'exchange_rate'        => (float) ($tx->exchange_rate ?? 1),
            'counterparty_name'    => $tx->counterparty_name,
            'counterparty_account' => $tx->counterparty_account,
            'description'          => $tx->description,
            'reference'            => $tx->reference,
            'account_name'         => $tx->account?->name,
            'category_name'        => $tx->category?->name,
            'vid_column'           => $tx->category?->vid_column,
            'cash_order_number'    => $tx->cashOrder?->number,
            'coredigify_sent_at'   => $tx->coredigify_sent_at?->toIso8601String(),
        ];
    }
}
