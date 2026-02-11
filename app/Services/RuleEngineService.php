<?php

namespace App\Services;

use App\Models\Rule;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class RuleEngineService
{
    protected ?Collection $rules = null;

    /**
     * Apply rules to a transaction or a batch.
     */
    public function applyRules(Transaction $transaction): bool
    {
        // Load rules if not loaded (cached per request)
        if (!$this->rules) {
            $this->rules = Rule::where('is_active', true)
                ->orderBy('priority', 'desc')
                ->get();
        }

        foreach ($this->rules as $rule) {
            if ($this->matches($transaction, $rule->criteria)) {
                $this->executeAction($transaction, $rule->action);
                return true; // Stop after first match? Or continue? Usually first match wins if priority is used.
            }
        }

        return false;
    }

    protected function matches(Transaction $transaction, ?array $criteria): bool
    {
        if (empty($criteria)) {
            return false;
        }

        // Check all criteria (AND logic)
        foreach ($criteria as $criterion) {
            $field = $criterion['field'] ?? null;
            $operator = $criterion['operator'] ?? 'contains';
            $value = $criterion['value'] ?? '';

            if (!$field) continue;

            $transactionValue = strtolower($transaction->{$field} ?? '');
            $value = strtolower($value);

            $match = match ($operator) {
                'equals' => $transactionValue === $value,
                'contains' => str_contains($transactionValue, $value),
                'starts_with' => str_starts_with($transactionValue, $value),
                'ends_with' => str_ends_with($transactionValue, $value),
                'gt' => $transactionValue > $value,
                'lt' => $transactionValue < $value,
                default => false,
            };

            if (!$match) {
                return false;
            }
        }

        return true;
    }

    protected function executeAction(Transaction $transaction, ?array $action): void
    {
        if (empty($action)) {
            return;
        }

        // Actions: ['set_category_id' => 123, 'set_type' => 'EXPENSE']
        // We can support multiple actions
        
        $updates = [];
        
        if (isset($action['category_id'])) {
            $updates['category_id'] = $action['category_id'];
        }
        
        if (isset($action['type'])) {
            $updates['type'] = $action['type'];
        }

        if (!empty($updates)) {
            $transaction->update($updates);
        }
    }
}
