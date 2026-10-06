<?php

namespace App\Services\Foundation;

use App\Events\Foundation\CompanyProfileUpdated;
use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompanyProfileService
{
    public function __construct(private readonly SettingService $settings) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Company $company, array $attributes, ?UploadedFile $logo = null, bool $removeLogo = false): Company
    {
        $storedPath = null;
        $previousPath = null;

        try {
            $updated = DB::transaction(function () use ($company, $attributes, $logo, $removeLogo, &$storedPath, &$previousPath) {
                $company->fill($this->attributes($attributes));

                if ($logo) {
                    $storedPath = $this->storeLogo($company, $logo);
                    $previousPath = $company->logo_path;
                    $company->logo_path = $storedPath;
                } elseif ($removeLogo && $company->logo_path) {
                    $previousPath = $company->logo_path;
                    $company->logo_path = null;
                }

                $company->save();

                $this->settings->write($company, [
                    'currency.code' => $company->currency_code,
                    'datetime.timezone' => $company->timezone,
                ], null, false);

                DB::afterCommit(fn () => event(new CompanyProfileUpdated($company)));

                return $company->refresh();
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }

            throw $exception;
        }

        if ($previousPath && $previousPath !== $updated->logo_path) {
            Storage::disk('public')->delete($previousPath);
        }

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function attributes(array $attributes): array
    {
        $data = Arr::only($attributes, [
            'name',
            'legal_name',
            'code',
            'email',
            'phone',
            'mobile',
            'website',
            'gstin',
            'pan',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
            'timezone',
            'currency_code',
            'fy_start_month',
        ]);

        foreach (['code', 'gstin', 'pan', 'currency_code'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = strtoupper($data[$field]);
            }
        }

        if (isset($data['fy_start_month'])) {
            $data['fy_start_month'] = (int) $data['fy_start_month'];
        }

        return $data;
    }

    private function storeLogo(Company $company, UploadedFile $file): string
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };

        if (! $extension || getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages([
                'logo' => 'The logo must be a JPEG, PNG, or WebP image.',
            ]);
        }

        return $file->storeAs(
            'companies/'.$company->uuid,
            'logo-'.Str::lower(Str::random(8)).'.'.$extension,
            'public',
        );
    }
}
