<?php
namespace VMP\Modules\AI\Context;

defined('ABSPATH') || exit;

interface PromptContextInterface
{
    /**
     * ToPromptContext functionality helper.
     *
     * @return array Output payload.
     */
    public function toPromptContext(): array;
}
