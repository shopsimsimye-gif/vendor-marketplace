<?php
namespace VMP\Modules\VendorRegistration\DTOs;

class StoreSetupDTO {
    public ?string $store_name;
    public ?string $store_slug;
    public ?string $description;
    public ?string $logo;
    public ?string $banner;
    public array $theme_settings;
    public ?string $address;
    public array $policies;
    public array $shipping_config;
    public array $payment_config;
    public array $social_links;

    public function __construct(array $data = [])
    {
        $this->store_name = $data['store_name'] ?? null;
        $this->store_slug = $data['store_slug'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->logo = $data['logo'] ?? null;
        $this->banner = $data['banner'] ?? null;
        $this->theme_settings = $data['theme_settings'] ?? [];
        $this->address = $data['address'] ?? null;
        $this->policies = $data['policies'] ?? [];
        $this->shipping_config = $data['shipping_config'] ?? [];
        $this->payment_config = $data['payment_config'] ?? [];
        $this->social_links = $data['social_links'] ?? [];
    }

    public function toArray(): array
    {
        return [
            'store_name' => $this->store_name,
            'store_slug' => $this->store_slug,
            'description' => $this->description,
            'logo' => $this->logo,
            'banner' => $this->banner,
            'theme_settings' => wp_json_encode($this->theme_settings),
            'address' => $this->address,
            'policies' => wp_json_encode($this->policies),
            'shipping_config' => wp_json_encode($this->shipping_config),
            'payment_config' => wp_json_encode($this->payment_config),
            'social_links' => wp_json_encode($this->social_links),
        ];
    }
}
