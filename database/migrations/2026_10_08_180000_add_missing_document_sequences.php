<?php

use App\Enums\DocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $existing = DB::table('document_sequences')
                ->where('company_id', $companyId)
                ->where('scope_key', 'company')
                ->pluck('document_type')
                ->all();

            foreach (DocumentType::cases() as $type) {
                if (in_array($type->value, $existing, true)) {
                    continue;
                }

                DB::table('document_sequences')->insert([
                    'company_id' => $companyId,
                    'uuid' => (string) Str::uuid(),
                    'scope_key' => 'company',
                    'document_type' => $type->value,
                    'prefix' => $type->defaultPrefix(),
                    'separator' => config('foundation.document_sequence.separator', '-'),
                    'padding' => config('foundation.document_sequence.padding', 4),
                    'next_number' => 1,
                    'reset_policy' => $type->defaultReset()->value,
                    'is_active' => true,
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void {}
};
