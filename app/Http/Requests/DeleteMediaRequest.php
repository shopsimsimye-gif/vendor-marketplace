<?php

declare(strict_types=1);

namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

use VMP\Http\Requests\AbstractRequest;
use VMP\Contracts\MediaRepositoryInterface;
use VMP\Core\Container;

class DeleteMediaRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        $mediaId = (int) $this->get('media_id', 0);
        if ($mediaId < 1) {
            return false;
        }

        $repository = Container::getInstance()->make(MediaRepositoryInterface::class);
        if (!$repository) {
            return false;
        }

        $media = $repository->find($mediaId);
        if (!$media) {
            return false;
        }

        return $media->vendorId === get_current_user_id() || current_user_can('manage_options');
    }

    protected function rules(): array
    {
        return [
            'media_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
