<?php
/**
 * الكلاس الأساسي لجميع طلبات الإدخال (Requests)
 *
 * @package VMP\Http\Requests
 * @since 3.0.0
 */

namespace VMP\Http\Requests;

defined('ABSPATH') || exit;

use VMP\Exceptions\ValidationException;
use VMP\Exceptions\AuthorizationException;

abstract class AbstractRequest
{
    protected array $data = [];
    private array $errors = [];
    private bool $validated = false;

    abstract protected function rules(): array;
    protected function messages(): array { return []; }
    protected function attributes(): array { return []; }
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void {}

    public static function from(array $data): static
    {
        $instance = new static();
        $instance->data = $data;
        return $instance;
    }

    public function setData(array $data): static
    {
        $this->data = $data;
        $this->validated = false;
        $this->errors = [];
        return $this;
    }

    /**
     * إنشاء Request من $_POST مع Nonce
     */
    public static function fromPost(string $nonce_action = '', string $nonce_field = '_wpnonce'): static
    {
        $instance = new static();

        if ($nonce_action) {
            $nonce = self::extractNonce($nonce_field);

            if (empty($nonce) || !wp_verify_nonce($nonce, $nonce_action)) {
                $instance->data = [];
                $instance->errors[] = __('رمز الأمان غير صالح أو منتهي الصلاحية.', 'vmp');
                // ❌ لا نُعيّن validated = true هنا!
                return $instance;
            }
        }

        $instance->data = wp_unslash($_POST);
        return $instance;
    }

    private static function extractNonce(string $nonce_field): string
    {
        $sources = [
            $_POST[$nonce_field] ?? null,
            $_POST['nonce'] ?? null,
            $_POST['security'] ?? null,
            $_SERVER['HTTP_X_WP_NONCE'] ?? null,
            $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null,
        ];

        foreach ($sources as $value) {
            if (!empty($value)) {
                return sanitize_text_field(wp_unslash($value));
            }
        }

        return '';
    }

    public static function fromRestRequest(\WP_REST_Request $request): static
    {
        $instance = new static();
        $instance->data = $request->get_params();
        return $instance;
    }

    public function validate(): bool
    {
        if (!$this->authorize()) {
            throw new AuthorizationException(__('غير مصرح لك بالقيام بهذا الإجراء.', 'vmp'));
        }

        if ($this->validated) {
            return empty($this->errors);
        }

        $this->prepareForValidation();
        $this->errors = [];
        $rules = $this->rules();

        foreach ($rules as $field => $fieldRules) {
            $value = $this->data[$field] ?? null;
            $label = $this->attributes()[$field] ?? $field;

            foreach ($fieldRules as $rule) {
                $error = $this->applyRule($rule, $field, $value, $label);
                if ($error !== null) {
                    $customKey = "{$field}.{$rule}";
                    $this->errors[] = $this->messages()[$customKey] ?? $this->messages()[$field] ?? $error;
                    break;
                }
            }
        }

        $this->validated = true;

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return true;
    }

    private function applyRule(string $rule, string $field, mixed $value, string $label): ?string
    {
        [$ruleName, $ruleParam] = array_pad(explode(':', $rule, 2), 2, null);

        if ($value === null || $value === '') {
            if ($ruleName === 'required') {
                return sprintf(__('حقل "%s" مطلوب.', 'vmp'), $label);
            }
            return null;
        }

        return match ($ruleName) {
            'required'  => null,
            'string'    => !is_string($value)
                            ? sprintf(__('حقل "%s" يجب أن يكون نصاً.', 'vmp'), $label)
                            : null,
            'numeric'   => !is_numeric($value)
                            ? sprintf(__('حقل "%s" يجب أن يكون رقماً.', 'vmp'), $label)
                            : null,
            'integer'   => filter_var($value, FILTER_VALIDATE_INT) === false
                            ? sprintf(__('حقل "%s" يجب أن يكون عدداً صحيحاً.', 'vmp'), $label)
                            : null,
            'float'     => filter_var($value, FILTER_VALIDATE_FLOAT) === false
                            ? sprintf(__('حقل "%s" يجب أن يكون رقماً عشرياً.', 'vmp'), $label)
                            : null,
            'min'       => mb_strlen((string) $value) < (int) $ruleParam
                            ? sprintf(__('حقل "%s" يجب أن يكون %d أحرف على الأقل.', 'vmp'), $label, $ruleParam)
                            : null,
            'max'       => mb_strlen((string) $value) > (int) $ruleParam
                            ? sprintf(__('حقل "%s" لا يمكن أن يتجاوز %d حرفاً.', 'vmp'), $label, $ruleParam)
                            : null,
            'min_value' => (float) $value < (float) $ruleParam
                            ? sprintf(__('قيمة "%s" يجب أن تكون أكبر من أو تساوي %s.', 'vmp'), $label, $ruleParam)
                            : null,
            'max_value' => (float) $value > (float) $ruleParam
                            ? sprintf(__('قيمة "%s" لا يمكن أن تتجاوز %s.', 'vmp'), $label, $ruleParam)
                            : null,
            'email'     => !is_email($value)
                            ? sprintf(__('حقل "%s" يجب أن يكون بريداً إلكترونياً صالحاً.', 'vmp'), $label)
                            : null,
            'url'       => !filter_var($value, FILTER_VALIDATE_URL)
                            ? sprintf(__('حقل "%s" يجب أن يكون رابطاً صالحاً.', 'vmp'), $label)
                            : null,
            'boolean'   => !in_array($value, [true, false, 0, 1, '0', '1'], true)
                            ? sprintf(__('حقل "%s" يجب أن يكون قيمة منطقية.', 'vmp'), $label)
                            : null,
            'array'     => !is_array($value)
                            ? sprintf(__('حقل "%s" يجب أن يكون مصفوفة.', 'vmp'), $label)
                            : null,
            'in'        => !in_array((string) $value, explode(',', (string) $ruleParam), true)
                            ? sprintf(__('قيمة "%s" غير مسموحة.', 'vmp'), $label)
                            : null,
            'not_in'    => in_array((string) $value, explode(',', (string) $ruleParam), true)
                            ? sprintf(__('قيمة "%s" غير مسموحة.', 'vmp'), $label)
                            : null,
            'regex'     => !preg_match($ruleParam, (string) $value)
                            ? sprintf(__('تنسيق حقل "%s" غير صالح.', 'vmp'), $label)
                            : null,
            'phone'     => !preg_match('/^\+?[0-9]{7,15}$/', preg_replace('/\s/', '', (string) $value))
                            ? sprintf(__('رقم الهاتف في حقل "%s" غير صالح.', 'vmp'), $label)
                            : null,
            default     => throw new \InvalidArgumentException(
                            sprintf(__('قاعدة تحقق غير معروفة: %s', 'vmp'), $ruleName)
                           ),
        };
    }

    public function isValid(): bool
    {
        try {
            $this->validate();
            return true;
        } catch (ValidationException | AuthorizationException $e) {
            return false;
        }
    }

    public function validated(): array
    {
        $this->validate();
        return array_intersect_key($this->data, $this->rules());
    }

    public function safe(): array
    {
        return $this->validated();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        return $this->errors()[0] ?? '';
    }

    protected function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        return sanitize_text_field((string) ($this->data[$key] ?? $default));
    }

    public function textarea(string $key, string $default = ''): string
    {
        return sanitize_textarea_field((string) ($this->data[$key] ?? $default));
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) ($this->data[$key] ?? $default);
    }

    public function float(string $key, float $default = 0.0): float
    {
        return (float) ($this->data[$key] ?? $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->data[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        return !in_array(strtolower((string) $value), ['0', 'false', 'no', 'off', ''], true);
    }

    public function all(): array
    {
        return $this->data;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->data, array_flip($keys));
    }
}
