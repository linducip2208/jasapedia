<?php

namespace App\Support\Money;

use InvalidArgumentException;

final readonly class Money
{
    public const CURRENCY = 'IDR';

    public function __construct(
        public int $amount,
        public string $currency = self::CURRENCY,
    ) {
        if ($this->currency !== self::CURRENCY) {
            throw new InvalidArgumentException('Only IDR is supported.');
        }
    }

    public static function of(int|string $amount): self
    {
        return new self((int) $amount);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        $this->assertSame($other);

        return new self($this->amount + $other->amount);
    }

    public function subtract(self $other): self
    {
        $this->assertSame($other);

        return new self($this->amount - $other->amount);
    }

    public function multiply(float|int $factor): self
    {
        return new self((int) round($this->amount * $factor));
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSame($other);

        return $this->amount < $other->amount;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSame($other);

        return $this->amount > $other->amount;
    }

    public function min(self $other): self
    {
        return $this->amount <= $other->amount ? $this : $other;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function format(): string
    {
        return 'Rp'.number_format($this->amount, 0, ',', '.');
    }

    public function jsonSerialize(): int
    {
        return $this->amount;
    }

    public function __toString(): string
    {
        return (string) $this->amount;
    }

    private function assertSame(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch.');
        }
    }
}
