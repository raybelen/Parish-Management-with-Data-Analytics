<?php

namespace App\Http\Requests;

use App\Support\WebsiteContent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWebsiteContentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('content.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'content' => ['required', 'array'],
        ];

        foreach (WebsiteContent::fields() as $key => $field) {
            $rules['content.'.$key] = ['required', 'string', 'max:'.$field['max']];

            if ($field['type'] === 'url') {
                $rules['content.'.$key][] = 'url:http,https';
            }

            if ($field['type'] === 'email') {
                $rules['content.'.$key][] = 'email:rfc';
            }
        }

        return $rules;
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $content = $this->input('content');

                if (! is_array($content)) {
                    return;
                }

                $unexpectedKeys = array_diff(array_keys($content), array_keys(WebsiteContent::fields()));

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('content', 'The content form contains an unsupported field.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $attributes = [];

        foreach (WebsiteContent::fields() as $key => $field) {
            $attributes['content.'.$key] = $field['label'];
        }

        return $attributes;
    }
}
