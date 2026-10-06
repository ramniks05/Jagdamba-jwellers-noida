<?php

namespace App\Services\Masters;

use App\Models\ChargeMethod;

class ChargeMethodService
{
    public function __construct(private readonly MasterRecordService $records) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ChargeMethod $method, array $attributes): ChargeMethod
    {
        /** @var ChargeMethod $method */
        $method = $this->records->update($method, $attributes);

        return $method;
    }

    public function delete(ChargeMethod $method): void
    {
        $this->records->delete($method);
    }
}
