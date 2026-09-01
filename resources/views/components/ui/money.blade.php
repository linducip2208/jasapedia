@props(['amount', 'currency' => 'Rp'])
<span {{ $attributes->merge(['class' => '']) }}>{{ (new \App\Support\Money\Money((int) $amount))->format() }}</span>
