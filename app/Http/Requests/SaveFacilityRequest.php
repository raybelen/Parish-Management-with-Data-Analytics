<?php

namespace App\Http\Requests;

use App\Models\Facility;
use App\Support\OfficeSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('facilities.manage')) {
            return false;
        }
        $facility = $this->route('facility');
        abort_if($facility instanceof Facility && $facility->source !== OfficeSettings::source(), 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('permitted_activities'))) {
            $this->merge(['permitted_activities' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $this->input('permitted_activities')))))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150', Rule::unique('facilities')->where('source', OfficeSettings::source())->ignore($this->route('facility'))],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'active' => ['required', 'boolean'],
            'permitted_activities' => ['required', 'array', 'list', 'min:1', 'max:30'],
            'permitted_activities.*' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'opening_hours' => ['required', 'array:1,2,3,4,5,6,7', 'size:7'],
        ];
        foreach (Facility::DAYS as $day => $label) {
            $rules["opening_hours.$day"] = ['required', 'array:open,opens_at,closes_at'];
            $rules["opening_hours.$day.open"] = ['required', 'boolean'];
            $rules["opening_hours.$day.opens_at"] = ["exclude_unless:opening_hours.$day.open,1", 'required', 'date_format:H:i'];
            $rules["opening_hours.$day.closes_at"] = ["exclude_unless:opening_hours.$day.open,1", 'required', 'date_format:H:i', "after:opening_hours.$day.opens_at"];
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    public function facilityData(): array
    {
        return $this->validated();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $attributes = [];
        foreach (Facility::DAYS as $day => $label) {
            $attributes["opening_hours.$day.opens_at"] = "$label opening time";
            $attributes["opening_hours.$day.closes_at"] = "$label closing time";
        }

        return $attributes;
    }
}
