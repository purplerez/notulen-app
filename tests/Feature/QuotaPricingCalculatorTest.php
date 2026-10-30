<?php

namespace Tests\Feature;

use App\Services\Billing\QuotaPricingCalculator;
use Tests\TestCase;

class QuotaPricingCalculatorTest extends TestCase
{
    protected QuotaPricingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new QuotaPricingCalculator;
    }

    public function test_it_calculates_standard_price_for_one_hour(): void
    {
        config(['quota.rate_per_hour_idr' => 50000]);

        $quote = $this->calculator->calculate(1);

        $this->assertSame(1, $quote['hours']);
        $this->assertSame(60, $quote['minutes']);
        $this->assertSame(50000.0, $quote['base_rate_per_hour']);
        $this->assertSame(0, $quote['discount_pct']);
        $this->assertSame(50000.0, $quote['total_amount']);
    }

    public function test_it_applies_ten_percent_discount_for_three_hours(): void
    {
        config(['quota.rate_per_hour_idr' => 50000]);

        $quote = $this->calculator->calculate(3);

        $this->assertSame(3, $quote['hours']);
        $this->assertSame(180, $quote['minutes']);
        $this->assertSame(10, $quote['discount_pct']);
        $this->assertSame(45000.0, $quote['effective_rate_per_hour']);
        $this->assertSame(135000.0, $quote['total_amount']);
    }

    public function test_it_applies_twenty_percent_discount_for_six_hours(): void
    {
        config(['quota.rate_per_hour_idr' => 50000]);

        $quote = $this->calculator->calculate(6);

        $this->assertSame(6, $quote['hours']);
        $this->assertSame(360, $quote['minutes']);
        $this->assertSame(20, $quote['discount_pct']);
        $this->assertSame(40000.0, $quote['effective_rate_per_hour']);
        $this->assertSame(240000.0, $quote['total_amount']);
    }

    public function test_it_applies_thirty_percent_discount_for_fifteen_hours(): void
    {
        config(['quota.rate_per_hour_idr' => 50000]);

        $quote = $this->calculator->calculate(15);

        $this->assertSame(15, $quote['hours']);
        $this->assertSame(900, $quote['minutes']);
        $this->assertSame(30, $quote['discount_pct']);
        $this->assertSame(35000.0, $quote['effective_rate_per_hour']);
        $this->assertSame(525000.0, $quote['total_amount']);
    }

    public function test_it_clamps_hours_within_min_and_max(): void
    {
        config([
            'quota.min_hours' => 1,
            'quota.max_hours' => 100,
            'quota.rate_per_hour_idr' => 50000,
        ]);

        $quoteUnder = $this->calculator->calculate(0);
        $this->assertSame(1, $quoteUnder['hours']);

        $quoteOver = $this->calculator->calculate(200);
        $this->assertSame(100, $quoteOver['hours']);
    }
}
