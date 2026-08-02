# Product Roadmap

This roadmap separates the stable Core Platform from future business features.

## 2.0.0 Core Platform

The Core Platform is treated as stable infrastructure. Changes should focus on bug fixes, performance, security, and platform contracts shared by multiple modules.

## 2.1 Vendor Experience

- New vendor dashboard.
- Store creation wizard.
- Quick actions.
- Notification center.
- Activity timeline.

## 2.2 Product AI

The seller uploads a product image, then the AI workflow can:

- Analyze the image with vision AI.
- Extract product attributes.
- Search external sources.
- Merge discovered information.
- Generate title, description, SEO, keywords, and specs.
- Generate promotional image assets.
- Publish the result as a draft product.

Initial architecture:

- `ProductGenerationPipeline` coordinates the workflow.
- `WorkflowEngine` provides a reusable engine for future Product, SEO, Advertisement, Translation, Pricing, and Competitor workflows.
- `ProductContext`, `VendorContext`, `StoreContext`, and `ImageContext` prepare prompt-ready context.
- Prompt templates live under `app/Modules/AI/Prompts`.
- `AIConfiguration` keeps provider, cache, review, and usage-limit settings outside the resolver.
- `AICache` prevents repeated provider calls for identical image/workflow inputs.
- `CostTracker` records tokens, latency, provider cost, image cost, and search cost.
- `AIResult` carries generated content, confidence, warnings, provider, latency, tokens, cost, sources, review status, and metadata.

AI-generated product content must be saved as a draft for human review before publishing.

## AI Commerce Suite Phases

### AI 2.1 Product Assistant

- Analyze product images.
- Extract specifications.
- Generate title, description, and SEO keywords.
- Create a reviewable product draft.

### AI 2.2 Marketing Assistant

- Generate promotional images.
- Generate Facebook, Instagram, Google, and TikTok ad copy.
- Prepare campaign-ready product snippets.

### AI 2.3 Pricing Assistant

- Analyze competitors.
- Suggest selling price.
- Estimate profit margin.
- Recommend best-price positioning.

### AI 2.4 Business Assistant

- Analyze sales.
- Suggest product opportunities.
- Forecast demand.
- Create smart alerts.

## 2.3 Smart Pricing

- Competitor analysis.
- Suggested selling price.
- Profit margin guidance.
- Best-price recommendations.

## 2.4 AI Assistant

- Improve product descriptions.
- Explain weak product performance.
- Suggest prices.
- Generate ad copy.

## 2.5 Analytics

- Smart dashboards.
- Weekly sales insights.
- Best publishing time recommendations.
- Product quality recommendations.

## 3.0 Marketplace Intelligence

- Trending products.
- Missing products.
- Selling opportunities.
- Competitor intelligence.
