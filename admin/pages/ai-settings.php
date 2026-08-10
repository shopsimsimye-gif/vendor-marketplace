<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── التحقق من الصلاحية ──
if (!current_user_can('vmp_manage_settings')) {
    wp_die(__('ليس لديك صلاحية للوصول إلى هذه الصفحة.', 'vmp'));
}

// ── جلب الإعدادات الحالية ──
$settings = get_option('vmp_ai_settings', []);
$defaults = [
    'default_provider'           => 'openai',
    'vision_provider'            => 'openai',
    'llm_provider'               => 'openai',
    'search_provider'            => 'openai',
    'image_generation_provider'  => 'openai',
    'cache_enabled'              => true,
    'cache_ttl'                  => 86400,
    'require_human_review'       => true,
    'default_status'             => 'draft',
    'monthly_vendor_cost_limit'  => 0,
    'monthly_vendor_request_limit' => 0,
    'openai_api_key'             => '',
    'openai_organization'        => '',
    'openai_model'               => 'gpt-4o',
    'openai_vision_model'        => 'gpt-4o',
    'openai_image_model'         => 'dall-e-3',
];
$settings = wp_parse_args($settings, $defaults);

// ── هل يوجد مفتاح محفوظ (مشفّر)؟ ──
$has_saved_key = !empty($settings['openai_api_key']) && !empty($settings['openai_api_key_encrypted']);
?>
<div class="wrap vmp-admin-wrap">
    <div class="vmp-admin-header">
        <h1><?php esc_html_e('إعدادات الذكاء الاصطناعي', 'vmp'); ?></h1>
        <p class="vmp-admin-subtitle"><?php esc_html_e('تهيئة مزودي خدمات الذكاء الاصطناعي المستخدمة في إنشاء المنتجات من الصور.', 'vmp'); ?></p>
    </div>

    <div id="vmp-ai-settings-notice" class="notice" hidden></div>

    <div class="vmp-card">
        <div class="vmp-card-body">
            <form id="vmp-ai-settings-form" class="vmp-ajax-form" data-action="vmp_admin_save_ai_settings">
                <?php wp_nonce_field('vmp_admin_nonce', 'nonce'); ?>

                <!-- ═══ المزودات الأساسية ═══ -->
                <h2 class="vmp-section-title"><?php esc_html_e('مزودات الذكاء الاصطناعي', 'vmp'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label><?php esc_html_e('المزود الافتراضي', 'vmp'); ?></label></th>
                        <td>
                            <select name="vmp_ai_settings[default_provider]" class="regular-text">
                                <option value="openai" <?php selected($settings['default_provider'], 'openai'); ?>>OpenAI</option>
                                <option value="gemini" <?php selected($settings['default_provider'], 'gemini'); ?>>Google Gemini</option>
                                <option value="claude" <?php selected($settings['default_provider'], 'claude'); ?>>Anthropic Claude</option>
                                <option value="ollama" <?php selected($settings['default_provider'], 'ollama'); ?>>Ollama (Local)</option>
                                <option value="openrouter" <?php selected($settings['default_provider'], 'openrouter'); ?>>OpenRouter</option>
                            </select>
                            <p class="description"><?php esc_html_e('المزود الذي سيتم استخدامه إذا لم يتم تحديد مزود محدد.', 'vmp'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('مزود تحليل الصور (Vision)', 'vmp'); ?></label></th>
                        <td>
                            <select name="vmp_ai_settings[vision_provider]" class="regular-text">
                                <option value="openai" <?php selected($settings['vision_provider'], 'openai'); ?>>OpenAI (GPT-4 Vision)</option>
                                <option value="gemini" <?php selected($settings['vision_provider'], 'gemini'); ?>>Google Gemini</option>
                                <option value="claude" <?php selected($settings['vision_provider'], 'claude'); ?>>Anthropic Claude</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('مزود النصوص (LLM)', 'vmp'); ?></label></th>
                        <td>
                            <select name="vmp_ai_settings[llm_provider]" class="regular-text">
                                <option value="openai" <?php selected($settings['llm_provider'], 'openai'); ?>>OpenAI (GPT-4)</option>
                                <option value="gemini" <?php selected($settings['llm_provider'], 'gemini'); ?>>Google Gemini</option>
                                <option value="claude" <?php selected($settings['llm_provider'], 'claude'); ?>>Anthropic Claude</option>
                                <option value="ollama" <?php selected($settings['llm_provider'], 'ollama'); ?>>Ollama (Local)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('مزود توليد الصور', 'vmp'); ?></label></th>
                        <td>
                            <select name="vmp_ai_settings[image_generation_provider]" class="regular-text">
                                <option value="openai" <?php selected($settings['image_generation_provider'], 'openai'); ?>>OpenAI (DALL-E)</option>
                                <option value="stable-diffusion" <?php selected($settings['image_generation_provider'], 'stable-diffusion'); ?>>Stable Diffusion</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('مزود البحث', 'vmp'); ?></label></th>
                        <td>
                            <select name="vmp_ai_settings[search_provider]" class="regular-text">
                                <option value="openai" <?php selected($settings['search_provider'], 'openai'); ?>>OpenAI (Web Search)</option>
                                <option value="google" <?php selected($settings['search_provider'], 'google'); ?>>Google Search</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- ═══ إعدادات الكاش ═══ -->
                <h2 class="vmp-section-title"><?php esc_html_e('التخزين المؤقت (Cache)', 'vmp'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label><?php esc_html_e('تفعيل التخزين المؤقت', 'vmp'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="vmp_ai_settings[cache_enabled]" value="1" <?php checked($settings['cache_enabled']); ?>>
                                <?php esc_html_e('تخزين نتائج الذكاء الاصطناعي مؤقتاً لتقليل التكلفة.', 'vmp'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('مدة التخزين المؤقت (بالثواني)', 'vmp'); ?></label></th>
                        <td>
                            <input type="number" name="vmp_ai_settings[cache_ttl]" class="regular-text" value="<?php echo esc_attr($settings['cache_ttl']); ?>" min="0" max="31536000">
                            <p class="description"><?php esc_html_e('افتراضي: 86400 (24 ساعة). الحد الأقصى: 31536000 (سنة).', 'vmp'); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- ═══ إعدادات المراجعة ═══ -->
                <h2 class="vmp-section-title"><?php esc_html_e('سياسة المراجعة والنشر', 'vmp'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label><?php esc_html_e('طلب مراجعة بشرية', 'vmp'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="vmp_ai_settings[require_human_review]" value="1" <?php checked($settings['require_human_review']); ?>>
                                <?php esc_html_e('يتطلب موافقة المشرف قبل نشر المنتجات التي تم إنشاؤها بواسطة الذكاء الاصطناعي.', 'vmp'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('الحالة الافتراضية للمنتج', 'vmp'); ?></label></th>
                        <td>
                            <select name="vmp_ai_settings[default_status]" class="regular-text">
                                <option value="draft" <?php selected($settings['default_status'], 'draft'); ?>><?php esc_html_e('مسودة', 'vmp'); ?></option>
                                <option value="pending" <?php selected($settings['default_status'], 'pending'); ?>><?php esc_html_e('قيد المراجعة', 'vmp'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- ═══ حدود الاستخدام ═══ -->
                <h2 class="vmp-section-title"><?php esc_html_e('حدود الاستخدام (لكل بائع)', 'vmp'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label><?php esc_html_e('الحد الأقصى للتكلفة الشهرية', 'vmp'); ?></label></th>
                        <td>
                            <input type="number" step="0.01" name="vmp_ai_settings[monthly_vendor_cost_limit]" class="regular-text" value="<?php echo esc_attr($settings['monthly_vendor_cost_limit']); ?>" min="0" max="1000000">
                            <p class="description"><?php esc_html_e('0 = غير محدود. الحد الأقصى: 1,000,000.', 'vmp'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('الحد الأقصى لعدد الطلبات الشهرية', 'vmp'); ?></label></th>
                        <td>
                            <input type="number" name="vmp_ai_settings[monthly_vendor_request_limit]" class="regular-text" value="<?php echo esc_attr($settings['monthly_vendor_request_limit']); ?>" min="0" max="1000000">
                            <p class="description"><?php esc_html_e('0 = غير محدود. الحد الأقصى: 1,000,000.', 'vmp'); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- ═══ مفاتيح API ═══ -->
                <h2 class="vmp-section-title"><?php esc_html_e('مفاتيح API', 'vmp'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label><?php esc_html_e('مفتاح OpenAI API', 'vmp'); ?></label></th>
                        <td>
                            <input type="password"
                                   name="vmp_ai_settings[openai_api_key]"
                                   class="regular-text"
                                   value=""
                                   autocomplete="new-password"
                                   placeholder="<?php echo $has_saved_key ? esc_attr__('اتركه فارغاً للإبقاء على المفتاح الحالي', 'vmp') : esc_attr__('أدخل مفتاح API جديد', 'vmp'); ?>">
                            <?php if ($has_saved_key) : ?>
                                <p class="description" style="color: #10b981; margin-top: 4px;">
                                    <?php esc_html_e('● مفتاح محفوظ ومؤمن', 'vmp'); ?>
                                </p>
                            <?php else : ?>
                                <p class="description"><?php esc_html_e('مطلوب لاستخدام خدمات OpenAI.', 'vmp'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('معرف المنظمة (اختياري)', 'vmp'); ?></label></th>
                        <td>
                            <input type="text" name="vmp_ai_settings[openai_organization]" class="regular-text" value="<?php echo esc_attr($settings['openai_organization']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('نموذج النصوص (LLM)', 'vmp'); ?></label></th>
                        <td>
                            <select name="vmp_ai_settings[openai_model]" class="regular-text">
                                <option value="gpt-4o" <?php selected($settings['openai_model'], 'gpt-4o'); ?>>GPT-4o</option>
                                <option value="gpt-4o-mini" <?php selected($settings['openai_model'], 'gpt-4o-mini'); ?>>GPT-4o mini</option>
                                <option value="gpt-4-turbo" <?php selected($settings['openai_model'], 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                <option value="gpt-4" <?php selected($settings['openai_model'], 'gpt-4'); ?>>GPT-4</option>
                                <option value="gpt-3.5-turbo" <?php selected($settings['openai_model'], 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                            </select>
                            <p class="description"><?php esc_html_e('يمكنك إضافة نموذج مخصص عن طريق كتابة اسمه مباشرة.', 'vmp'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('نموذج الرؤية (Vision)', 'vmp'); ?></label></th>
                        <td>
                            <select name="vmp_ai_settings[openai_vision_model]" class="regular-text">
                                <option value="gpt-4o" <?php selected($settings['openai_vision_model'], 'gpt-4o'); ?>>GPT-4o</option>
                                <option value="gpt-4o-mini" <?php selected($settings['openai_vision_model'], 'gpt-4o-mini'); ?>>GPT-4o mini</option>
                                <option value="gpt-4-turbo" <?php selected($settings['openai_vision_model'], 'gpt-4-turbo'); ?>>GPT-4 Turbo (Vision)</option>
                                <option value="gpt-4-vision-preview" <?php selected($settings['openai_vision_model'], 'gpt-4-vision-preview'); ?>>GPT-4 Vision Preview</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e('نموذج توليد الصور', 'vmp'); ?></label></th>
                        <td>
                            <select name="vmp_ai_settings[openai_image_model]" class="regular-text">
                                <option value="dall-e-3" <?php selected($settings['openai_image_model'], 'dall-e-3'); ?>>DALL-E 3</option>
                                <option value="dall-e-2" <?php selected($settings['openai_image_model'], 'dall-e-2'); ?>>DALL-E 2</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary button-large"><?php esc_html_e('حفظ إعدادات الذكاء الاصطناعي', 'vmp'); ?></button>
                </p>
            </form>
        </div>
    </div>
</div>


<style>
.vmp-section-title {
    margin: 30px 0 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e2e8f0;
    font-size: 18px;
    font-weight: 600;
}
.form-table th {
    width: 220px;
}
</style>
