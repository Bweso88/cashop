<?php

namespace App\Domain\Payments\Services;

use App\Support\Http\ApiException;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Taux de change (table fx_rates) : taux direct, inverse, ou croisé via l'EUR.
 * Calculs en décimal exact (brick/math), jamais en virgule flottante.
 */
class FxService
{
    private const SCALE = 12;

    /** @var array<string, int> */
    private array $decimals = [];

    /**
     * @return array{rate: BigDecimal, market: BigDecimal, spread_bps: int, source: string}
     *                                                                                      rate = taux client (marge déduite), market = taux de référence
     */
    public function rate(string $from, string $to): array
    {
        if ($from === $to) {
            return ['rate' => BigDecimal::one(), 'market' => BigDecimal::one(), 'spread_bps' => 0, 'source' => 'IDENTITY'];
        }

        [$market, $spread, $source] = $this->direct($from, $to)
            ?? $this->cross($from, $to)
            ?? throw new ApiException('FX_RATE_UNAVAILABLE', "Aucun taux de change configuré pour {$from} → {$to}.", 422);

        $rate = $market->multipliedBy(BigDecimal::of(10_000 - $spread)->dividedBy(10_000, self::SCALE, RoundingMode::Down))
            ->toScale(self::SCALE, RoundingMode::Down);

        return ['rate' => $rate, 'market' => $market, 'spread_bps' => $spread, 'source' => $source];
    }

    /** Montant (unités mineures) converti au taux donné, arrondi à l'unité mineure inférieure. */
    public function convert(int $amount, string $from, string $to, BigDecimal $rate): int
    {
        $shift = $this->decimals($to) - $this->decimals($from);
        $value = BigDecimal::of($amount)->multipliedBy($rate);
        $value = $shift >= 0
            ? $value->multipliedBy(BigInteger::ten()->power($shift))
            : $value->dividedBy(BigInteger::ten()->power(-$shift), self::SCALE, RoundingMode::Down);

        return $value->toScale(0, RoundingMode::Down)->toInt();
    }

    public function decimals(string $currency): int
    {
        return $this->decimals[$currency] ??= (int) DB::table('currencies')->where('code', $currency)->value('minor_units');
    }

    /** @return array{0: BigDecimal, 1: int, 2: string}|null */
    private function direct(string $from, string $to): ?array
    {
        if ($row = $this->latest($from, $to)) {
            return [BigDecimal::of($row->rate), (int) $row->spread_bps, $row->source];
        }
        if ($row = $this->latest($to, $from)) {
            return [BigDecimal::one()->dividedBy($row->rate, self::SCALE, RoundingMode::Down), (int) $row->spread_bps, $row->source.'_INVERSE'];
        }

        return null;
    }

    /** @return array{0: BigDecimal, 1: int, 2: string}|null */
    private function cross(string $from, string $to): ?array
    {
        if ($from === 'EUR' || $to === 'EUR') {
            return null;
        }
        $first = $this->direct($from, 'EUR');
        $second = $this->direct('EUR', $to);
        if (! $first || ! $second) {
            return null;
        }

        return [
            $first[0]->multipliedBy($second[0])->toScale(self::SCALE, RoundingMode::Down),
            max($first[1], $second[1]),
            "CROSS({$first[2]},{$second[2]})",
        ];
    }

    private function latest(string $base, string $quote): ?object
    {
        return DB::table('fx_rates')
            ->where('base_currency', $base)->where('quote_currency', $quote)
            ->where('valid_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', now()))
            ->orderByDesc('valid_from')
            ->first();
    }
}
