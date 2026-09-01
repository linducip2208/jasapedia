<?php

namespace Tests\Unit\Support;

use App\Support\Money\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_adds_and_subtracts(): void
    {
        $a = Money::of(100000);
        $b = Money::of(25000);

        $this->assertSame(125000, $a->add($b)->amount);
        $this->assertSame(75000, $a->subtract($b)->amount);
    }

    public function test_multiply_rounds_to_int(): void
    {
        $this->assertSame(3350, Money::of(10000)->multiply(0.335)->amount);
    }

    public function test_negative_detection(): void
    {
        $this->assertTrue(Money::of(-1)->isNegative());
        $this->assertFalse(Money::zero()->isNegative());
    }

    public function test_formats_idr(): void
    {
        $this->assertSame('Rp1.250.000', Money::of(1250000)->format());
    }

    public function test_rejects_non_idr(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Money(100, 'USD');
    }
}
