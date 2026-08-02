<?php
namespace VMP\DTO;

defined('ABSPATH') || exit;

use JsonSerializable;

/**
 * الكلاس الأساسي لنقل البيانات (DTO)
 * 
 * يفرض بناء كائن من مصفوفة وتصديره لمصفوفة وJSON.
 * 
 * @package VMP\DTO
 */
abstract class BaseDTO implements JsonSerializable
{
    /**
     * بناء DTO من مصفوفة بيانات
     *
     * @param array $data
     * @return static
     */
    abstract public static function fromArray(array $data): static;

    /**
     * تحويل الـ DTO إلى مصفوفة
     *
     * @return array
     */
    abstract public function toArray(): array;

    /**
     * التوافقية مع JsonSerializable
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
