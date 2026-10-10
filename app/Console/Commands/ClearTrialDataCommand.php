<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ClearTrialDataCommand extends Command
{
    protected $signature = 'shop:clear-trial-data {--force : Skip the confirmation prompt}';

    protected $description = 'Delete trial customers, bills, stock, orders, suppliers and rates, keeping the shop setup';

    /**
     * Children before parents, so the order also works where foreign keys stay enforced.
     *
     * @var list<string>
     */
    private const TABLES = [
        'whatsapp_messages',
        'document_number_allocations',
        'ledger_entries',
        'payments',
        'inventory_transactions',
        'sale_line_stones',
        'sale_return_lines',
        'sale_returns',
        'purchase_return_lines',
        'purchase_returns',
        'old_gold_movements',
        'old_gold_exchanges',
        'repair_orders',
        'scheme_installments',
        'scheme_enrollments',
        'gold_schemes',
        'girvi_pledge_items',
        'girvi_pledges',
        'advance_orders',
        'sale_lines',
        'sales',
        'purchase_lines',
        'purchases',
        'item_stones',
        'items',
        'customer_intakes',
        'metal_rates',
        'suppliers',
    ];

    public function handle(): int
    {
        $tables = array_values(array_filter(self::TABLES, fn (string $table): bool => Schema::hasTable($table)));
        $counts = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);
        $customers = DB::table('customers')->where('is_system', false)->count();

        $this->table(['Table', 'Rows to delete'], $counts
            ->filter()
            ->map(fn (int $count, string $table): array => [$table, $count])
            ->push(['customers (walk-in kept)', $customers])
            ->values()
            ->all());
        $this->line('Kept: shop profile, branches, users, roles, settings, financial years, masters, stock locations, walk-in customer.');
        $this->line('Bill and receipt numbers restart from the first number.');

        if (! $this->option('force') && $this->ask('Type DELETE to remove this data for good') !== 'DELETE') {
            $this->warn('Nothing was deleted.');

            return self::FAILURE;
        }

        Schema::withoutForeignKeyConstraints(function () use ($tables): void {
            DB::transaction(function () use ($tables): void {
                foreach ($tables as $table) {
                    DB::table($table)->delete();
                }

                DB::table('customers')->where('is_system', false)->delete();
                DB::table('document_sequences')->update([
                    'next_number' => 1,
                    'last_period_key' => null,
                    'last_issued_number' => null,
                    'last_issued_at' => null,
                ]);
            });
        });

        Storage::disk('public')->deleteDirectory('items');

        $this->info('Trial data deleted. The shop is ready for real billing.');

        return self::SUCCESS;
    }
}
