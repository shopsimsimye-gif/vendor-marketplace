# AI Module

This module boundary is reserved for AI Commerce Suite features.

Keep provider-specific code behind `VMP\Contracts\AI` interfaces so workflows can support OpenAI, Gemini, Claude, Ollama, OpenRouter, and OpenAI-compatible APIs without changing business code.

Planned responsibilities:

- Product image understanding.
- Product title, description, SEO, keywords, and specs generation.
- Promotional image generation.
- AI assistant workflows for vendors.
- AI orchestration across vision, search, LLM, and image providers.

Current foundation:

- `AIModule` is the loadable module entry point.
- `AIServiceProvider` binds provider-neutral AI contracts.
- `ProviderResolver` isolates provider selection from orchestration.
- `AIConfiguration` centralizes provider selection, cache settings, review policy, and usage limits.
- `AIOrchestrator` exposes high-level AI capabilities to feature workflows.
- `Context` classes convert product, vendor, store, and image data into prompt-ready context.
- `Prompts` keep prompt templates out of pipelines and services.
- `ProductGenerationPipeline` coordinates the first image-to-product-draft workflow.
- `WorkflowEngine` supports reusable multi-step AI workflows beyond a single pipeline.
- `AICache` prepares image/workflow caching before expensive provider calls.
- `CostTracker` normalizes token, image, search, latency, and cost data.
- `AIResult` provides a typed result object for human review screens and draft publishing.
- `AIJobRepository` stores product-generation jobs in `vmp_ai_jobs` instead of WordPress options.
- `ProcessAIProductDraftJob` runs product generation through the shared `vmp_jobs` queue and WP-Cron worker.
- Unconfigured providers fail explicitly with `AIException` until a real adapter is registered.

Product generation workflow:

1. Upload creates an AI job with workflow `product-image-v1`.
2. The HTTP request returns immediately with state `QUEUED`.
3. `ProcessAIProductDraftJob` moves the job through explicit states: `ANALYZING_IMAGE`, `SEARCHING`, `GENERATING_TITLE`, `GENERATING_DESCRIPTION`, `GENERATING_SEO`, and `REVIEW`.
4. The vendor UI polls `vmp_ai_get_product_job` and survives page/network delays because state, logs, result, retries, cost, tokens, and latency are persisted.
5. Provider failures are retried once before a fallback draft is generated for human review.

Publishing rule:

- AI creates drafts and recommendations only.
- Vendors review generated content before publishing.
- `AIResult::reviewStatus` tracks whether the result is pending review or approved.

First user-facing feature:

- `vendor-ai-create-product.php` provides the "Create Product From Image" review screen.
- `vendor-ai-product.js` handles upload, progress, targeted regeneration, and draft publishing.
- `AIProductDraftService` creates queued AI jobs, records workflow state, falls back gracefully when no provider is configured, and creates the WooCommerce product only after vendor review.
