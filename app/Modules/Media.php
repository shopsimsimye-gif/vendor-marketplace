<?php

declare(strict_types=1);

namespace VMP\Modules;

defined('ABSPATH') || exit;

use VMP\Contracts\MediaRepositoryInterface;
use VMP\Core\Container;

class Media extends AbstractModule
{
    public function init(): void
    {
        add_action('delete_user', [$this, 'cleanupVendorMedia'], 10, 1);
        add_action('before_delete_post', [$this, 'cleanupProductMedia'], 10, 1);
    }

    protected function make(string $abstract): mixed
    {
        return $this->container->make($abstract);
    }

    public function cleanupVendorMedia(int $userId): void
    {
        if (!user_can($userId, 'vmp_vendor')) {
            return;
        }
        $repository = $this->make(MediaRepositoryInterface::class);
        if ($repository) {
            $repository->deleteByVendor($userId);
        }
    }

    public function cleanupProductMedia(int $postId): void
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'product') {
            return;
        }
        delete_post_meta($postId, '_vmp_featured_media_id');
    }
}
