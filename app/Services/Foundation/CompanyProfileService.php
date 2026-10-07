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
    public function __construct(
        private readonly SettingService $settings,
        private readonly SignatureCutout $signatures,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        Company $company,
        array $attributes,
        ?UploadedFile $logo = null,
        bool $removeLogo = false,
        ?UploadedFile $signature = null,
        bool $removeSignature = false,
    ): Company {
        $storedPaths = [];
        $retiredPaths = [];

        try {
            $updated = DB::transaction(function () use ($company, $attributes, $logo, $removeLogo, $signature, $removeSignature, &$storedPaths, &$retiredPaths) {
                $company->fill($this->attributes($attributes));
                $this->replaceImage($company, 'logo_path', $logo, $removeLogo, 'logo', $storedPaths, $retiredPaths);
                $this->replaceImage($company, 'signature_path', $signature, $removeSignature, 'signature', $storedPaths, $retiredPaths);

                $company->save();

                $this->settings->write($company, [
                    'currency.code' => $company->currency_code,
                    'datetime.timezone' => $company->timezone,
                ], null, false);

                DB::afterCommit(fn () => event(new CompanyProfileUpdated($company)));

                return $company->refresh();
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('public')->delete($storedPaths);
            }

            throw $exception;
        }

        if ($retiredPaths !== []) {
            Storage::disk('public')->delete($retiredPaths);
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

    /**
     * @param  list<string>  $storedPaths
     * @param  list<string>  $retiredPaths
     */
    private function replaceImage(
        Company $company,
        string $column,
        ?UploadedFile $file,
        bool $remove,
        string $field,
        array &$storedPaths,
        array &$retiredPaths,
    ): void {
        if ($file) {
            $path = $this->storeImage($company, $file, $field);
            $storedPaths[] = $path;

            if ($company->{$column}) {
                $retiredPaths[] = $company->{$column};
            }

            $company->{$column} = $path;

            return;
        }

        if ($remove && $company->{$column}) {
            $retiredPaths[] = $company->{$column};
            $company->{$column} = null;
        }
    }

    private function storeImage(Company $company, UploadedFile $file, string $field): string
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };

        if (! $extension || getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages([
                $field => 'The '.$field.' must be a JPEG, PNG, or WebP image.',
            ]);
        }

        if ($field === 'signature') {
            $png = $this->signatures->png($file->getRealPath());

            if ($png !== null) {
                $path = 'companies/'.$company->uuid.'/signature-'.Str::lower(Str::random(8)).'.png';
                Storage::disk('public')->put($path, $png);

                return $path;
            }
        }

        return $file->storeAs(
            'companies/'.$company->uuid,
            $field.'-'.Str::lower(Str::random(8)).'.'.$extension,
            'public',
        );
    }
}
