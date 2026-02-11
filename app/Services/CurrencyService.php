<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CurrencyService
{
    /**
     * Convert amount between currencies.
     * Uses ECB rates (stubbed for now).
     */
    public function convert(float $amount, string $from, string $to = 'EUR', ?Carbon $date = null): float
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = $this->getRate($from, $to, $date);
        
        return round($amount * $rate, 6);
    }

    /**
     * Get exchange rate from Source to Target.
     * Currently returns 1.0 for testing, logic to be implemented with ECB API.
     */
    public function getRate(string $from, string $to, ?Carbon $date = null): float
    {
        if ($from === $to) {
            return 1.0;
        }

        // Placeholder: Manual hardcoded rates or fetch from DB/API
        // In a real implementation:
        // 1. Check DB for stored rate on $date
        // 2. If missing, fetch from ECB/API
        
        // For MVP, we'll assume 1.0 or throw exception if critical
        // But for development let's allow USD->EUR at fixed rate to test logic
        if ($from === 'USD' && $to === 'EUR') {
            return 0.92; // Approx rate
        }
        
        return 1.0;
    }
}
