<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class BaseFormRequest extends FormRequest
{
    /**
     * Assignment expects invalid payloads as 400 (not Laravel’s default 422).
     * Each field maps to a single string (first message) instead of an array of messages.
     */
    protected function failedValidation(Validator $validator): void
    {
        $flattened = collect($validator->errors()->messages())
            ->map(fn(array $messages) => implode(', ', $messages))
            ->all();

        $exception = new ValidationException($validator);

        throw new ValidationException(
            $validator,
            response()->json([
                'message' => $exception->getMessage(),
                'errors' => $flattened,
            ], Response::HTTP_BAD_REQUEST)
        );
    }
}
