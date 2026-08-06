<?php

declare(strict_types=1);

namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

use VMP\Http\Requests\AbstractRequest;

class UploadMediaRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return current_user_can('vmp_vendor') || current_user_can('manage_options');
    }

    protected function rules(): array
    {
        return [
            'file' => ['required'],
        ];
    }
}
