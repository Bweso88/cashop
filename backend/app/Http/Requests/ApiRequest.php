<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base des requêtes API : l'autorisation fine est faite par les policies et middlewares.
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Règles communes de description d'appareil. */
    protected function deviceRules(): array
    {
        return [
            'device' => ['required', 'array'],
            'device.device_id' => ['required', 'string', 'max:100'],
            'device.platform' => ['required', 'in:ios,android,web'],
            'device.name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
