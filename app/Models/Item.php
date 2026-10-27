<?php

namespace App\Models;

use App\Enums\ItemSource;
use App\Enums\ItemStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable([
    'company_id', 'branch_id', 'stock_location_id', 'sku', 'item_code', 'barcode', 'rfid', 'name',
    'category_id', 'brand_id', 'collection_id', 'design_id', 'metal_type_id', 'purity_id',
    'gross_weight', 'stone_weight', 'other_weight', 'net_weight',
    'making_method_id', 'making_value', 'wastage_method_id', 'wastage_value', 'stone_value',
    'cost_price', 'selling_price', 'mrp', 'certificate_number', 'hallmark', 'huid', 'image_path',
    'status', 'source', 'own_gold', 'notes',
])]
class Item extends Model
{
    use BelongsToCompany, HasPublicUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ItemStatus::class,
            'source' => ItemSource::class,
            'own_gold' => 'boolean',
            'gross_weight' => 'decimal:3',
            'stone_weight' => 'decimal:3',
            'other_weight' => 'decimal:3',
            'net_weight' => 'decimal:3',
            'making_value' => 'decimal:4',
            'wastage_value' => 'decimal:4',
            'stone_value' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'mrp' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }

    public function metalType(): BelongsTo
    {
        return $this->belongsTo(MetalType::class);
    }

    public function purity(): BelongsTo
    {
        return $this->belongsTo(Purity::class);
    }

    public function makingMethod(): BelongsTo
    {
        return $this->belongsTo(ChargeMethod::class, 'making_method_id');
    }

    public function wastageMethod(): BelongsTo
    {
        return $this->belongsTo(ChargeMethod::class, 'wastage_method_id');
    }

    public function stones(): HasMany
    {
        return $this->hasMany(ItemStone::class)->orderBy('position')->orderBy('id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function purchaseLines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    /**
     * @param  Builder<Item>  $query
     * @return Builder<Item>
     */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $query) use ($like) {
            $query->where('name', 'like', $like)
                ->orWhere('item_code', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('barcode', 'like', $like)
                ->orWhere('huid', 'like', $like);
        });
    }

    public function deletionBlocker(): ?string
    {
        if ($this->own_gold) {
            return 'Own-gold pieces are removed from the Own gold screen, not here.';
        }

        if ($this->status !== ItemStatus::Available) {
            return 'Only an available piece with no bills can be removed. Change the status back first if it was reserved by mistake.';
        }

        foreach ([
            'sale_lines' => ['item_id', 'bill'],
            'purchase_lines' => ['item_id', 'purchase'],
            'repair_orders' => ['item_id', 'repair job'],
            'sale_return_lines' => ['item_id', 'return'],
            'purchase_return_lines' => ['item_id', 'purchase return'],
        ] as $table => [$column, $label]) {
            $count = DB::table($table)->where($column, $this->getKey())->count();

            if ($count > 0) {
                return 'This piece is on '.$count.' '.Str::plural($label, $count).' and cannot be removed.';
            }
        }

        if (DB::table('old_gold_movements')->where('item_id', $this->getKey())->exists()) {
            return 'This piece is linked to an old-gold exchange and cannot be removed.';
        }

        if ($this->movements()->count() !== 1) {
            return 'This piece has stock history beyond the first entry. It cannot be removed.';
        }

        return null;
    }
}
