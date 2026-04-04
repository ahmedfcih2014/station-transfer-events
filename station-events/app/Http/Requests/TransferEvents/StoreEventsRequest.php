<?php

namespace App\Http\Requests\TransferEvents;

use App\Http\Requests\BaseFormRequest;
use App\Rules\Iso8601DateTime;

class StoreEventsRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'events' => 'required|array',
            'events.*.event_id' => 'required|string|max:255',
            'events.*.station_id' => 'required|string|max:255',
            'events.*.amount' => 'required|numeric|min:0',
            // hint: according to design notes at readme we'll process only approved events
            'events.*.status' => 'required|string|max:255|in:approved',
            'events.*.created_at' => ['required', 'string', new Iso8601DateTime()],
        ];
    }

    public function attributes(): array
    {
        return [
            'events.*.event_id' => 'Event ID',
            'events.*.station_id' => 'Station ID',
            'events.*.amount' => 'Amount',
            'events.*.status' => 'Status',
            'events.*.created_at' => 'Created At',
        ];
    }
}
