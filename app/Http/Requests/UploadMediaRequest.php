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
        // [QA 2026-08-07] الملف يُقرأ من $_FILES وليس $_POST؛ قاعدة 'required' هنا كانت
        // تفشل دائماً لأن validate() يفحص $_POST فقط. التحقق الفعلي من الملف (وجود/حجم/MIME)
        // يجري في MediaController::upload() و MediaService::upload().
        return [];
    }
}
