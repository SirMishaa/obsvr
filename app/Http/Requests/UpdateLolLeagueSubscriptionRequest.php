<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LolSubscriptionMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLolLeagueSubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(LolSubscriptionMode::class)],
            'team_codes' => ['exclude_unless:mode,teams', 'required', 'array', 'min:1', 'max:20'],
            'team_codes.*' => ['string', 'max:10', 'distinct'],
            'costream_channel' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_]{3,25}$/'],
        ];
    }
}
