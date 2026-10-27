<?php

namespace App\Services\Commerce;

use App\Enums\TrayMovementKind;
use App\Models\Company;
use App\Models\Sale;
use App\Models\StockTray;
use App\Models\TrayMovement;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrayService
{
    public function __construct(private readonly CompanyContext $context) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, array $attributes, ?int $userId = null): StockTray
    {
        return DB::transaction(function () use ($company, $attributes, $userId) {
            $this->context->ensureId((int) $company->id);
            $grams = $this->grams($attributes['grams'] ?? 0);
            $pieces = (int) $attributes['pieces'];

            if ($pieces < 1) {
                throw ValidationException::withMessages([
                    'pieces' => 'Enter how many pieces are on the tray.',
                ]);
            }

            if ($grams->isNegativeOrZero()) {
                throw ValidationException::withMessages([
                    'grams' => 'Enter the tray weight in grams.',
                ]);
            }

            $tray = StockTray::query()->create([
                'company_id' => $company->id,
                'code' => $attributes['code'],
                'name' => $attributes['name'],
                'grams' => '0',
                'pieces' => 0,
                'sort_order' => (int) ($attributes['sort_order'] ?? 0),
                'is_active' => filter_var($attributes['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ]);

            $this->move($tray, TrayMovementKind::Opening, $grams, $pieces, null, 'Opening balance', $userId);

            return $tray->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(StockTray $tray, array $attributes, ?int $userId = null): StockTray
    {
        return DB::transaction(function () use ($tray, $attributes, $userId) {
            $this->context->ensureId((int) $tray->company_id);

            $tray->fill([
                'code' => $attributes['code'],
                'name' => $attributes['name'],
                'sort_order' => (int) ($attributes['sort_order'] ?? 0),
                'is_active' => filter_var($attributes['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]);

            if (array_key_exists('grams', $attributes) || array_key_exists('pieces', $attributes)) {
                $targetGrams = $this->grams($attributes['grams'] ?? $tray->grams);
                $targetPieces = (int) ($attributes['pieces'] ?? $tray->pieces);

                if ($targetPieces < 0) {
                    throw ValidationException::withMessages([
                        'pieces' => 'Pieces cannot be negative.',
                    ]);
                }

                if ($targetGrams->isNegative()) {
                    throw ValidationException::withMessages([
                        'grams' => 'Grams cannot be negative.',
                    ]);
                }

                $gramsDelta = $targetGrams->minus((string) $tray->grams);
                $piecesDelta = $targetPieces - (int) $tray->pieces;

                if (! $gramsDelta->isZero() || $piecesDelta !== 0) {
                    $this->move($tray, TrayMovementKind::Adjustment, $gramsDelta, $piecesDelta, null, 'Balance corrected', $userId);
                }
            }

            $tray->save();

            return $tray->refresh();
        });
    }

    public function delete(StockTray $tray): void
    {
        DB::transaction(function () use ($tray) {
            $this->context->ensureId((int) $tray->company_id);
            $locked = StockTray::query()->whereKey($tray->id)->lockForUpdate()->firstOrFail();

            if ($message = $locked->deletionBlocker()) {
                throw ValidationException::withMessages(['record' => $message]);
            }

            $locked->movements()->delete();
            $locked->delete();
        });
    }

    public function recordSale(StockTray $tray, Sale $sale, string $netGrams, ?int $userId = null): void
    {
        DB::transaction(function () use ($tray, $sale, $netGrams, $userId) {
            $this->context->ensureId((int) $tray->company_id);
            $locked = StockTray::query()->whereKey($tray->id)->lockForUpdate()->firstOrFail();
            $grams = BigDecimal::of($netGrams)->toScale(3, RoundingMode::HalfUp);

            if ($grams->isNegativeOrZero()) {
                throw ValidationException::withMessages([
                    'tray_uuid' => 'This bill has no net weight to take from the tray.',
                ]);
            }

            if (BigDecimal::of((string) $locked->grams)->isLessThan($grams)) {
                throw ValidationException::withMessages([
                    'tray_uuid' => 'Tray '.$locked->name.' has only '.$locked->grams.' g left.',
                ]);
            }

            if ((int) $locked->pieces < 1) {
                throw ValidationException::withMessages([
                    'tray_uuid' => 'Tray '.$locked->name.' has no pieces left.',
                ]);
            }

            $this->move($locked, TrayMovementKind::Sale, $grams->negated(), -1, $sale, 'Sold on '.$sale->number, $userId);
        });
    }

    public function reverseSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $movements = TrayMovement::query()
                ->where('reference_type', $sale->getMorphClass())
                ->where('reference_id', $sale->id)
                ->where('kind', TrayMovementKind::Sale)
                ->lockForUpdate()
                ->get();

            foreach ($movements as $movement) {
                $tray = StockTray::query()->whereKey($movement->stock_tray_id)->lockForUpdate()->first();

                if ($tray) {
                    $tray->grams = (string) BigDecimal::of((string) $tray->grams)
                        ->minus((string) $movement->grams_delta)
                        ->toScale(3, RoundingMode::HalfUp);
                    $tray->pieces = (int) $tray->pieces - (int) $movement->pieces_delta;
                    $tray->save();
                }

                $movement->delete();
            }
        });
    }

    private function move(
        StockTray $tray,
        TrayMovementKind $kind,
        BigDecimal $gramsDelta,
        int $piecesDelta,
        ?Sale $reference,
        ?string $notes,
        ?int $userId,
    ): void {
        $nextGrams = BigDecimal::of((string) $tray->grams)->plus($gramsDelta)->toScale(3, RoundingMode::HalfUp);
        $nextPieces = (int) $tray->pieces + $piecesDelta;

        if ($nextGrams->isNegative()) {
            throw ValidationException::withMessages([
                'grams' => 'Tray weight cannot go below zero.',
            ]);
        }

        if ($nextPieces < 0) {
            throw ValidationException::withMessages([
                'pieces' => 'Tray pieces cannot go below zero.',
            ]);
        }

        TrayMovement::query()->create([
            'company_id' => $tray->company_id,
            'stock_tray_id' => $tray->id,
            'kind' => $kind,
            'grams_delta' => (string) $gramsDelta->toScale(3, RoundingMode::HalfUp),
            'pieces_delta' => $piecesDelta,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'notes' => $notes,
            'user_id' => $userId,
            'occurred_at' => now(),
        ]);

        $tray->grams = (string) $nextGrams;
        $tray->pieces = $nextPieces;
        $tray->save();
    }

    private function grams(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) $value)->toScale(3, RoundingMode::HalfUp);
    }
}
