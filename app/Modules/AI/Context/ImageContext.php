<?php
namespace VMP\Modules\AI\Context;

defined('ABSPATH') || exit;

/**
 * Class ImageContext
 *
 * Description of administrative platform component ImageContext.
 *
 * @package vendor-marketplace
 */
class ImageContext implements PromptContextInterface
{
    public function __construct(
        public readonly string $image = '',
        public readonly int $attachmentId = 0,
        public readonly string $alt = ''
    ) {
    }

    /**
     * FromArray functionality helper.
     *
     * @param array $data Description index.
     * @return self Output payload.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            image: (string) ($data['image'] ?? ''),
            attachmentId: (int) ($data['attachment_id'] ?? 0),
            alt: (string) ($data['alt'] ?? '')
        );
    }

    /**
     * ToPromptContext functionality helper.
     *
     * @return array Output payload.
     */
    public function toPromptContext(): array
    {
        return [
            'image' => [
                'source' => $this->image,
                'attachment_id' => $this->attachmentId,
                'alt' => $this->alt,
            ],
        ];
    }
}
