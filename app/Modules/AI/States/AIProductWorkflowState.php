<?php
namespace VMP\Modules\AI\States;

defined('ABSPATH') || exit;

final class AIProductWorkflowState
{
    public const UPLOADED = 'UPLOADED';
    public const QUEUED = 'QUEUED';
    public const ANALYZING_IMAGE = 'ANALYZING_IMAGE';
    public const OCR = 'OCR';
    public const BARCODE = 'BARCODE';
    public const SEARCHING = 'SEARCHING';
    public const MERGING = 'MERGING';
    public const GENERATING_TITLE = 'GENERATING_TITLE';
    public const GENERATING_DESCRIPTION = 'GENERATING_DESCRIPTION';
    public const GENERATING_SEO = 'GENERATING_SEO';
    public const GENERATING_KEYWORDS = 'GENERATING_KEYWORDS';
    public const GENERATING_ATTRIBUTES = 'GENERATING_ATTRIBUTES';
    public const GENERATING_IMAGES = 'GENERATING_IMAGES';
    public const REVIEW = 'REVIEW';
    public const DRAFT = 'DRAFT';
    public const PUBLISHED = 'PUBLISHED';
    public const FAILED = 'FAILED';
}
