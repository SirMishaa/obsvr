<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TwitchEventSubRequest extends FormRequest
{
    public array $jsonPayload = [];

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            '_headers.id' => ['required', 'string'],
            '_headers.type' => ['required', 'in:webhook_callback_verification,notification,revocation'],
            '_headers.timestamp' => ['required', 'date'],
            '_headers.signature' => ['required', 'string', 'regex:/^sha256=/'],
            '_raw' => ['required', 'string'],

            /** Flexible fields */
            'subscription' => ['sometimes', 'array'],
            'subscription.type' => ['sometimes', 'string'],

            'subscription.condition' => ['sometimes', 'array'],
            'subscription.condition.broadcaster_user_id' => ['sometimes', 'string'],

            'event' => ['sometimes', 'array'],
            'challenge' => ['sometimes', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            '_headers' => [
                'id' => $this->header('Twitch-Eventsub-Message-Id'),
                'type' => $this->header('Twitch-Eventsub-Message-Type'),
                'timestamp' => $this->header('Twitch-Eventsub-Message-Timestamp'),
                'signature' => $this->header('Twitch-Eventsub-Message-Signature'),
            ],
            '_raw' => $this->getContent(),
        ]);

        $this->jsonPayload = json_decode($this->getContent(), true) ?: [];
    }
}
