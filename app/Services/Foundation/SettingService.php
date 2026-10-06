<?php

namespace App\Services\Foundation;

use App\Enums\SettingValueType;
use App\Events\Foundation\SettingsUpdated;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Setting;
use App\Support\CompanyContext;
use App\Support\TenantScopeKey;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SettingService
{
    /**
     * @var array<string, mixed>
     */
    private array $cache = [];

    public function get(string $key, ?Company $company = null, ?Branch $branch = null): mixed
    {
        $meta = $this->meta($key);
        $company ??= app(CompanyContext::class)->company();

        if ($company) {
            app(CompanyContext::class)->ensureId((int) $company->id);
        }

        if (! $company) {
            return $this->castDefault($meta);
        }

        $cacheKey = $company->id.'|'.TenantScopeKey::forBranch($branch?->id).'|'.$key;

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        if ($branch) {
            $override = $this->find($company, $key, $branch);
            if ($override) {
                return $this->cache[$cacheKey] = $override->typedValue();
            }
        }

        $stored = $this->find($company, $key, null);

        return $this->cache[$cacheKey] = $stored ? $stored->typedValue() : $this->castDefault($meta);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(Company $company, array $values, ?Branch $branch = null): void
    {
        DB::transaction(function () use ($company, $values, $branch): void {
            $this->write($company, $values, $branch, true);
            DB::afterCommit(fn () => event(new SettingsUpdated($company, array_keys($values))));
        });
    }

    public function seedDefaults(Company $company): void
    {
        $values = [];

        foreach (config('foundation.settings') as $key => $meta) {
            $values[$key] = $meta['default'];
        }

        $values['currency.code'] = $company->currency_code;
        $values['datetime.timezone'] = $company->timezone;

        $this->write($company, $values, null, false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function formFields(?Company $company = null): array
    {
        $fields = [];

        foreach (config('foundation.settings') as $key => $meta) {
            $fields[] = [
                'key' => $key,
                'group' => $meta['group'],
                'group_label' => config('foundation.groups.'.$meta['group'], $meta['group']),
                'label' => $meta['label'],
                'help' => $meta['help'] ?? null,
                'type' => $meta['type'],
                'input' => $meta['input'] ?? 'text',
                'options' => $meta['options'] ?? null,
                'value' => $this->get($key, $company),
            ];
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function write(Company $company, array $values, ?Branch $branch = null, bool $syncCompany = true): void
    {
        app(CompanyContext::class)->ensureId((int) $company->id);

        if ($branch && (int) $branch->company_id !== (int) $company->id) {
            throw new RuntimeException('Branch does not belong to this company.');
        }

        foreach ($values as $key => $value) {
            $meta = $this->meta($key);

            if ($key === 'currency.code' && is_string($value)) {
                $value = strtoupper($value);
            }

            Setting::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'scope_key' => TenantScopeKey::forBranch($branch?->id),
                    'key' => $key,
                ],
                [
                    'branch_id' => $branch?->id,
                    'group' => $meta['group'],
                    'value' => $this->serialize($meta['type'], $value),
                    'value_type' => SettingValueType::from($meta['type']),
                ],
            );
        }

        if ($syncCompany) {
            if (array_key_exists('currency.code', $values)) {
                $company->currency_code = strtoupper((string) $values['currency.code']);
            }

            if (array_key_exists('datetime.timezone', $values)) {
                $company->timezone = (string) $values['datetime.timezone'];
            }

            if ($company->isDirty()) {
                $company->save();
            }
        }

        $this->cache = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(string $key): array
    {
        $catalog = config('foundation.settings');

        if (! is_array($catalog) || ! isset($catalog[$key]) || ! is_array($catalog[$key])) {
            throw new InvalidArgumentException("Unknown setting [$key].");
        }

        return $catalog[$key];
    }

    private function find(Company $company, string $key, ?Branch $branch): ?Setting
    {
        return Setting::query()
            ->where('company_id', $company->id)
            ->where('scope_key', TenantScopeKey::forBranch($branch?->id))
            ->where('key', $key)
            ->first();
    }

    private function serialize(string $type, mixed $value): ?string
    {
        $valueType = SettingValueType::from($type);

        if ($value === null && $valueType !== SettingValueType::Boolean) {
            return null;
        }

        return match ($valueType) {
            SettingValueType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            SettingValueType::Integer => (string) (int) $value,
            SettingValueType::Decimal => (string) $value,
            SettingValueType::Json => json_encode($value, JSON_THROW_ON_ERROR),
            SettingValueType::String => (string) $value,
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function castDefault(array $meta): mixed
    {
        $setting = new Setting([
            'value' => $this->serialize($meta['type'], $meta['default']),
            'value_type' => $meta['type'],
        ]);

        return $setting->typedValue();
    }
}
