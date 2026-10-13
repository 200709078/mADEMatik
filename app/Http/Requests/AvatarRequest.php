<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->is_blocked;
    }

    public function rules(): array
    {
        return ['avatar' => ['bail', 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => __('profile.avatar_required'),
            'avatar.uploaded' => __('profile.avatar_invalid'),
            'avatar.file' => __('profile.avatar_invalid'),
            'avatar.image' => __('profile.avatar_invalid'),
            'avatar.mimes' => __('profile.avatar_invalid'),
            'avatar.max' => __('profile.avatar_too_large'),
        ];
    }

    public function attributes(): array
    {
        return ['avatar' => __('profile.avatar_title')];
    }
}
