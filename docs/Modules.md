# Modular Feature Roadmap

The Core Platform is considered stable infrastructure. New business capabilities should be built as independent modules on top of Core rather than placed directly inside Core namespaces.

## Core Platform

Core owns the reusable foundation:

- Architecture, dependency injection, service providers, repositories, DTOs, requests, policies, events, queue, middleware, REST, cache, security, tests, and documentation.
- Core changes should be limited to bug fixes, performance improvements, and platform-level contracts that multiple modules need.

## Business Modules

Business features should live under `app/Modules`.

Reserved module boundaries:

- `AI`: AI Commerce Suite, product enrichment, assistant workflows, content generation, orchestration.
- `Wallet`: balances, payouts, financial ledger, vendor wallet.
- `Shipping`: rates, labels, carriers, fulfillment workflows.
- `CRM`: customer/vendor communication and retention workflows.
- `POS`: point-of-sale and offline selling workflows.
- `Analytics`: smart dashboards, insights, recommendations, marketplace intelligence.
- `Mobile`: mobile APIs, push workflows, app-specific behavior.

## AI Provider Contracts

AI integrations must depend on provider-neutral contracts:

- `VMP\Contracts\AI\VisionProviderInterface`
- `VMP\Contracts\AI\LLMProviderInterface`
- `VMP\Contracts\AI\ImageGenerationProviderInterface`
- `VMP\Contracts\AI\SearchProviderInterface`

Provider adapters should normalize OpenAI, Gemini, Claude, Ollama, OpenRouter, and OpenAI-compatible APIs behind these contracts.

The default AI module binds these contracts to unconfigured providers that throw `AIException`. Real providers should replace those bindings in an AI provider-specific service provider.

The AI module exposes `VMP\Modules\AI\AIOrchestrator` as a thin coordination layer. Feature workflows should use it to access provider capabilities without knowing which vendor is configured.

AI feature code should use these layers:

- `Context`: convert WordPress, WooCommerce, vendor, store, and image data into prompt-ready arrays.
- `Prompts`: keep prompt templates as isolated classes.
- `Pipelines`: coordinate multi-step workflows such as image analysis, search, merge, generation, validation, and result creation.
- `Results`: return typed result objects such as `AIResult` instead of loose arrays.
- `ProviderResolver`: decide which configured provider implementation should satisfy each AI capability.
- `AIConfiguration`: centralize default providers, capability-specific providers, cache settings, review policy, and usage limits.
- `Cost`: track token usage, image cost, search cost, latency, vendor cost, and monthly usage.
- `Cache`: reuse expensive AI results with image and workflow keys before calling external providers.
- `Workflows`: compose reusable AI steps for Product, SEO, Advertisement, Translation, Pricing, and Competitor workflows.

## Rule

Do not add new business feature code to Core unless the change is a reusable platform capability needed by more than one module.
