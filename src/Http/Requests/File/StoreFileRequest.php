<?php

namespace Luchavez\SimpleFiles\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Class StoreFileRequest
 *
 * @author James Carlo Luchavez <jamescarloluchavez@gmail.com>
 */
class StoreFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'preserve_name' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            //
        ]);
    }
}
