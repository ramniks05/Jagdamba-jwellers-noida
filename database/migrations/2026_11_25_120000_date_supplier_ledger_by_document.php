<?php

use App\Models\Payment;
use App\Models\Purchase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Supplier account rows used to be stamped with the day they were typed in. A bill dated last
     * month or a payment made last week then showed up under today. Give each row its document's
     * own date so the supplier passbook reads in the order things really happened.
     */
    public function up(): void
    {
        $this->redate((new Purchase)->getMorphClass(), 'purchases', 'purchased_at');
        $this->redate((new Payment)->getMorphClass(), 'payments', 'received_at');
    }

    public function down(): void
    {
        // The typed-in dates are not kept, so there is nothing to put back.
    }

    private function redate(string $referenceType, string $table, string $column): void
    {
        DB::table('ledger_entries')
            ->where('party_type', 'supplier')
            ->where('reference_type', $referenceType)
            ->orderBy('id')
            ->chunkById(200, function ($entries) use ($table, $column): void {
                $dates = DB::table($table)->whereIn('id', $entries->pluck('reference_id'))->pluck($column, 'id');

                foreach ($entries as $entry) {
                    $date = $dates[$entry->reference_id] ?? null;

                    if ($date !== null && $date !== $entry->occurred_at) {
                        DB::table('ledger_entries')->where('id', $entry->id)->update(['occurred_at' => $date]);
                    }
                }
            });
    }
};
