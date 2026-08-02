(function($) {
    'use strict';

    var pollTimer = null;
    var workflowId = 'product-image-v1';

    var stepMessages = {
        UPLOADED: 'تم رفع الصورة.',
        QUEUED: 'تمت إضافة المهمة للطابور.',
        ANALYZING_IMAGE: 'جاري تحليل الصورة...',
        SEARCHING: 'جاري البحث وجمع المواصفات...',
        GENERATING_TITLE: 'جاري توليد العنوان...',
        GENERATING_DESCRIPTION: 'جاري توليد الوصف...',
        GENERATING_SEO: 'جاري تجهيز SEO والكلمات المفتاحية...',
        REVIEW: 'المسودة جاهزة للمراجعة.',
        PUBLISHED: 'تم إرسال المنتج للمراجعة.',
        FAILED: 'فشلت عملية إنشاء المسودة.'
    };

    function setProgress(percent, step, message) {
        $('#vmp-ai-progress-percent').text(percent + '%');
        $('#vmp-ai-progress-fill').css('width', percent + '%');
        $('.vmp-ai-steps li').removeClass('active done');
        var reached = true;
        $('.vmp-ai-steps li').each(function() {
            var $item = $(this);
            if ($item.data('step') === step) {
                $item.addClass('active');
                reached = false;
            } else if (reached) {
                $item.addClass('done');
            }
        });
        $('#vmp-ai-status-message').text(message || stepMessages[step] || '');
    }

    function normalizeError(response, fallback) {
        // Provider-level message
        if (response && response.data && response.data.message) {
            return response.data.message;
        }

        // Validation errors (WP REST style)
        if (response && response.data && response.data.errors && Array.isArray(response.data.errors)) {
            return response.data.errors.map(function(e) { return e.message || e; }).join('\n');
        }

        // Provider-specific errors array
        if (response && response.data && response.data.provider_errors) {
            if (Array.isArray(response.data.provider_errors)) {
                return response.data.provider_errors.join('\n');
            }
            return String(response.data.provider_errors);
        }

        if (response && response.responseJSON && response.responseJSON.data && response.responseJSON.data.message) {
            return response.responseJSON.data.message;
        }

        if (response && response.statusText === 'timeout') {
            return 'انتهت مهلة الطلب. سيتم حفظ حالة المهمة ويمكنك المحاولة لاحقاً.';
        }

        // XHR with JSON payload
        if (response && response.responseJSON && response.responseJSON.message) {
            return response.responseJSON.message;
        }

        return fallback || vmp_public.strings.error;
    }

    function escapeText(value) {
        return $('<div>').text(value || '').html();
    }

    function renderTags($target, values) {
        $target.empty();
        if (!values || (Array.isArray(values) && values.length === 0)) {
            $target.append('<span class="vmp-ai-tag muted">-</span>');
            return;
        }

        if (!Array.isArray(values)) {
            values = Object.keys(values).map(function(key) {
                return key + ': ' + values[key];
            });
        }

        values.forEach(function(value) {
            $target.append('<span class="vmp-ai-tag">' + escapeText(value) + '</span>');
        });
    }

    function renderLogs(logs) {
        var $panel = $('#vmp-ai-log-panel');
        var $list = $('#vmp-ai-log-list');
        $list.empty();

        if (!Array.isArray(logs) || logs.length === 0) {
            $panel.prop('hidden', true);
            $('#vmp-ai-log-toggle').prop('hidden', true);
            return;
        }

        $('#vmp-ai-log-toggle').prop('hidden', false);
        logs.slice().reverse().forEach(function(log) {
            var level = escapeText(log.level || 'info');
            var at = escapeText(log.at || '');
            var message = escapeText(log.message || '');
            $list.append(
                '<li class="vmp-ai-log-item vmp-ai-log-' + level + '">' +
                    '<span>' + at + '</span>' +
                    '<strong>' + level + '</strong>' +
                    '<p>' + message + '</p>' +
                '</li>'
            );
        });
    }

    function renderResult(job) {
        var result = job.result || {};
        $('#vmp-ai-job-id').val(job.id);
        $('#vmp-ai-title').val(result.title || '');
        $('#vmp-ai-description').val(result.description || '');
        $('#vmp-ai-short-description').val(result.short_description || '');
        $('#vmp-ai-confidence').text(Math.round((result.confidence || 0) * 100) + '%');
        $('#vmp-ai-provider').text(result.provider || '-');
        $('#vmp-ai-latency').text((result.latency_ms || 0) + 'ms');
        $('#vmp-ai-cost').text('$' + Number(result.cost || 0).toFixed(3));
        $('#vmp-ai-tokens').text(result.tokens || 0);

        renderTags($('#vmp-ai-specifications'), result.specifications || []);
        renderTags($('#vmp-ai-keywords'), result.keywords || []);

        var warnings = result.warnings || [];
        var $warnings = $('#vmp-ai-warnings');
        $warnings.empty().prop('hidden', warnings.length === 0);
        warnings.forEach(function(warning) {
            $warnings.append('<div>' + escapeText(warning) + '</div>');
        });

        $('#vmp-ai-review-card').prop('hidden', false);
        setProgress(100, 'REVIEW', stepMessages.REVIEW);
    }

    function renderJob(job) {
        var step = job.current_step || job.step || job.status || 'QUEUED';
        setProgress(job.progress || 0, step, job.error || stepMessages[step]);

        if (job.status === 'FAILED') {
            alert(job.error || stepMessages.FAILED);
            return true;
        }

        if (job.result && (job.status === 'REVIEW' || job.progress >= 100)) {
            renderResult(job);
            return true;
        }

        return false;
    }

    function request(action, data, options) {
        data = data || {};
        data.action = action;
        data.nonce = vmp_public.nonce;
        data.workflow_id = data.workflow_id || workflowId;

        return new Promise(function(resolve, reject) {
            $.ajax($.extend({
                url: vmp_public.ajax_url,
                method: 'POST',
                data: data,
                timeout: 30000
            }, options || {})).done(function(response) {
                if (!response || !response.success) {
                    return reject(new Error(normalizeError(response)));
                }
                resolve(response.data);
            }).fail(function(xhr) {
                reject(new Error(normalizeError(xhr, vmp_public.strings.conn_error)));
            });
        });
    }

    function postAction(action, data, done) {
        return request(action, data).then(function(responseData) {
            if (typeof done === 'function') {
                done(responseData);
            }
            return responseData;
        }).catch(function(error) {
            alert(error.message || vmp_public.strings.error);
            throw error;
        });
    }

    function renderTimeline(events) {
        // ensure container exists
        var $container = $('#vmp-ai-timeline');
        if ($container.length === 0) {
            $container = $('<div id="vmp-ai-timeline" class="vmp-ai-timeline" />');
            $container.insertAfter('#vmp-ai-status-message');
        }

        $container.empty();
        if (!Array.isArray(events) || events.length === 0) {
            $container.append('<div class="vmp-ai-timeline-empty">' + escapeText(vmp_public.strings.no_timeline || 'لا توجد أحداث بعد') + '</div>');
            return;
        }

        var $list = $('<ul class="vmp-ai-timeline-list"></ul>');
        events.forEach(function(ev) {
            var time = ev.created_at || '';
            var type = ev.type || ev.event_type || 'event';
            var payload = ev.payload || {};
            var message = '';
            if (payload.message) {
                message = escapeText(payload.message);
            } else if (payload.step) {
                message = escapeText(payload.step);
            } else if (payload.level && payload.message) {
                message = '<strong>' + escapeText(payload.level) + '</strong>: ' + escapeText(payload.message);
            } else if (payload.provider) {
                message = 'Provider: ' + escapeText(payload.provider);
            }

            var $item = $('<li class="vmp-ai-timeline-item vmp-ai-timeline-' + escapeText(type.toLowerCase()) + '"></li>');
            $item.append('<div class="vmp-ai-timeline-time">' + escapeText(time) + '</div>');
            $item.append('<div class="vmp-ai-timeline-type">' + escapeText(type) + '</div>');
            if (message) {
                $item.append('<div class="vmp-ai-timeline-msg">' + message + '</div>');
            }
            $list.append($item);
        });

        $container.append('<h3>' + (vmp_public.strings.timeline_title || 'Timeline') + '</h3>');
        $container.append($list);
    }

    async function pollJob(jobId) {
        if (pollTimer) {
            window.clearTimeout(pollTimer);
        }

        try {
            var data = await request('vmp_ai_get_product_job', { job_id: jobId });
            var finished = renderJob(data.job);

            // fetch timeline and render
            try {
                var tl = await request('vmp_ai_get_job_timeline', { job_id: jobId });
                renderTimeline(tl.events || []);
            } catch (timelineErr) {
                // ignore timeline errors but log to UI
                console.warn('Timeline fetch failed', timelineErr);
            }

            if (!finished) {
                pollTimer = window.setTimeout(function() {
                    pollJob(jobId);
                }, 2000);
            }
        } catch (error) {
            $('#vmp-ai-status-message').text(error.message || vmp_public.strings.conn_error);
            // backoff before retrying poll
            pollTimer = window.setTimeout(function() {
                pollJob(jobId);
            }, 5000);
        }
    }

    $(function() {
        if (vmp_public.page !== 'ai-create-product') {
            return;
        }

        $('#vmp-ai-product-image').on('change', function() {
            var file = this.files && this.files[0];
            var $preview = $('#vmp-ai-image-preview');
            if (!file) {
                $preview.prop('hidden', true).empty();
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                $preview.html('<img src="' + e.target.result + '" alt="">').prop('hidden', false);
            };
            reader.readAsDataURL(file);
        });

        $('#vmp-ai-product-upload').on('submit', async function(e) {
            e.preventDefault();
            var form = this;
            var formData = new FormData(form);
            var $button = $(form).find('button[type="submit"]');

            // Ensure required fields are present in the FormData
            formData.set('action', 'vmp_ai_create_product_from_image');
            formData.set('nonce', vmp_public.nonce);
            formData.set('workflow_id', workflowId);

            setProgress(10, 'UPLOADED', 'تم رفع الصورة، جاري إنشاء المهمة...');
            $button.prop('disabled', true).text('جاري جدولة المهمة...');

            try {
                var response = await new Promise(function(resolve, reject) {
                    $.ajax({
                        url: vmp_public.ajax_url,
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        timeout: 30000
                    }).done(function(resp) {
                        if (!resp || !resp.success) {
                            return reject(new Error(normalizeError(resp)));
                        }
                        resolve(resp.data);
                    }).fail(function(xhr) {
                        reject(new Error(normalizeError(xhr, vmp_public.strings.conn_error)));
                    });
                });

                $('#vmp-ai-job-id').val(response.job.id);
                renderJob(response.job);
                pollJob(response.job.id);
            } catch (err) {
                alert(err.message || vmp_public.strings.conn_error);
            } finally {
                $button.prop('disabled', false).text('إنشاء المسودة');
            }
        });

        $('.vmp-ai-regenerate').on('click', function() {
            var part = $(this).data('part');
            var locked = $('.vmp-ai-lock[data-part="' + part + '"]').is(':checked');
            if (locked) {
                alert('هذا الجزء مقفل حالياً.');
                return;
            }

            var $button = $(this);
            var originalText = $button.text();
            $button.prop('disabled', true).text('جاري...');
            postAction('vmp_ai_regenerate_product_part', {
                job_id: $('#vmp-ai-job-id').val(),
                part: part
            }, function(data) {
                renderResult(data.job);
            }).finally(function() {
                $button.prop('disabled', false).text(originalText);
            });
        });

        $('#vmp-ai-review-form').on('submit', function(e) {
            e.preventDefault();
            var $button = $(this).find('button[type="submit"]');
            var data = {
                job_id: $('#vmp-ai-job-id').val(),
                title: $('#vmp-ai-title').val(),
                description: $('#vmp-ai-description').val(),
                short_description: $('#vmp-ai-short-description').val(),
                regular_price: $('#vmp-ai-price').val()
            };

            var originalText = $button.text();
            $button.prop('disabled', true).text('جاري الإرسال...');
            postAction('vmp_ai_publish_product_draft', data, function(response) {
                alert(response.message || 'تم إنشاء المنتج.');
                window.location.href = response.edit_url || '?vmp_page=products';
            }).finally(function() {
                $button.prop('disabled', false).text(originalText);
            });
        });
    });
})(jQuery);
