<?php
namespace VMP\Modules\AI\Context;

defined('ABSPATH') || exit;

/**
 * Class ProductContext
 *
 * Description of administrative platform component ProductContext.
 *
 * @package vendor-marketplace
 */
class ProductContext implements PromptContextInterface
{
    public function __construct(
        public readonly string $title = '',
        public readonly string $description = '',
        public readonly array $attributes = [],
        public readonly array $categories = [],
        public readonly array $tags = [],
        public readonly string $locale = 'ar'
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
            title: (string) ($data['title'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            attributes: is_array($data['attributes'] ?? null) ? $data['attributes'] : [],
            categories: is_array($data['categories'] ?? null) ? $data['categories'] : [],
            tags: is_array($data['tags'] ?? null) ? $data['tags'] : [],
            locale: (string) ($data['locale'] ?? 'ar')
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
            'product' => [
                'title' => $this->title,
                'description' => $this->description,
                'attributes' => $this->attributes,
                'categories' => $this->categories,
                'tags' => $this->tags,
            ],
            'locale' => $this->locale,
        ];
    }
}
