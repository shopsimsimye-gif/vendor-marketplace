<?php
namespace VMP\Services;

defined('ABSPATH') || exit;

use VMP\Contracts\VendorRepositoryInterface;
use Exception;

/**
 * Class WhatsappService
 *
 * Description of administrative platform component WhatsappService.
 *
 * @package vendor-marketplace
 */
class WhatsappService
{
    private string $clicksTable;

    public function __construct(
        private VendorRepositoryInterface $vendorRepository,
        private SubscriptionService $subscriptionService,
        private \wpdb $db
    ) {
        $this->clicksTable = $this->db->prefix . 'vmp_whatsapp_clicks';
    }

    /**
     * تسجيل نقرة على زر واتساب
     *
     * @param int $vendorId معرف البائع
     * @param int $productId معرف المنتج (اختياري)
     * @param string $pageUrl رابط الصفحة
     * @param string $clickType نوع النقرة
     * @param string $userAgent
     * @param string $referrer
     * @return void
     */
    public function trackClick(int $vendorId, int $productId, string $pageUrl, string $clickType, string $userAgent = '', string $referrer = ''): void
    {
        $this->db->insert($this->clicksTable, [
            'vendor_id' => $vendorId,
            'product_id' => $productId ?: null,
            'page_url' => substr($pageUrl, 0, 255),
            'click_type' => substr($clickType, 0, 50),
            'user_agent' => substr($userAgent, 0, 255),
            'referrer' => substr($referrer, 0, 500),
            'clicked_at' => current_time('mysql'),
        ]);
    }

    /**
     * حفظ إعدادات واتساب الخاصة بالبائع
     *
     * @param int $vendorId
     * @param string $whatsappNumber
     * @param string $whatsappMessage
     * @return void
     * @throws Exception
     */
    public function saveSettings(int $vendorId, string $whatsappNumber, string $whatsappMessage): void
    {
        $vendor = $this->vendorRepository->find($vendorId);
        if (!$vendor) {
            throw new Exception(__('البائع غير موجود', 'vmp'));
        }

        if (!$this->subscriptionService->hasFeature($vendorId, 'whatsapp_button')) {
            throw new Exception(__('هذه الميزة غير متاحة في خطتك الحالية', 'vmp'));
        }

        if (!empty($whatsappNumber) && !preg_match('/^\+?[0-9]{7,15}$/', preg_replace('/\s/', '', $whatsappNumber))) {
            throw new Exception(__('رقم الواتساب غير صالح', 'vmp'));
        }

        $updated = $this->vendorRepository->update($vendorId, [
            'whatsapp_number' => $whatsappNumber,
            'whatsapp_message' => $whatsappMessage,
        ]);

        if (!$updated) {
            throw new Exception(__('لم يتم إجراء أي تغييرات', 'vmp'));
        }
    }

    /**
     * الحصول على إحصائيات نقرات واتساب لبائع معين
     *
     * @param int $vendorId
     * @param string $period (today, week, month, all)
     * @return array
     */
    public function getVendorStats(int $vendorId, string $period = 'month'): array
    {
        $where = "WHERE vendor_id = %d";
        $params = [$vendorId];

        if ($period === 'today') {
            $where .= " AND DATE(clicked_at) = CURDATE()";
        } elseif ($period === 'week') {
            $where .= " AND clicked_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $where .= " AND clicked_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }

        $sqlTotal = "SELECT COUNT(*) FROM {$this->clicksTable} $where";
        $totalClicks = (int) $this->db->get_var($this->db->prepare($sqlTotal, $params));

        $sqlTopProducts = "SELECT product_id, COUNT(*) as clicks FROM {$this->clicksTable} $where AND product_id IS NOT NULL GROUP BY product_id ORDER BY clicks DESC LIMIT 5";
        $topProductsRows = $this->db->get_results($this->db->prepare($sqlTopProducts, $params));

        $topProducts = [];
        foreach ($topProductsRows as $row) {
            $productName = get_the_title($row->product_id);
            $topProducts[] = [
                'id' => $row->product_id,
                'name' => $productName ?: __('منتج محذوف', 'vmp'),
                'clicks' => (int) $row->clicks,
            ];
        }

        return [
            'total_clicks' => $totalClicks,
            'top_products' => $topProducts,
        ];
    }

    /**
     * إحصائيات واتساب الإجمالية للمشرف
     *
     * @param string $period
     * @return array
     */
    public function getAdminStats(string $period = 'month'): array
    {
        $where = "WHERE 1=1";
        if ($period === 'today') {
            $where .= " AND DATE(clicked_at) = CURDATE()";
        } elseif ($period === 'week') {
            $where .= " AND clicked_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        } elseif ($period === 'month') {
            $where .= " AND clicked_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }

        $total = (int) $this->db->get_var("SELECT COUNT(*) FROM {$this->clicksTable} $where");
        $byVendor = $this->db->get_results(
            "SELECT vendor_id, COUNT(*) as clicks FROM {$this->clicksTable} $where GROUP BY vendor_id ORDER BY clicks DESC LIMIT 10"
        );

        return [
            'total_clicks' => $total,
            'top_vendors'  => $byVendor,
        ];
    }

    /**
     * بيانات الرسم البياني للنقرات (للمشرف)
     *
     * @param int $days
     * @return array
     */
    public function getChartData(int $days = 30): array
    {
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT DATE(clicked_at) as date, COUNT(*) as clicks
                FROM {$this->clicksTable}
                WHERE clicked_at >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
                GROUP BY DATE(clicked_at)
                ORDER BY date ASC",
                $days
            )
        );

        return array_map(fn($r) => [
            'date'   => $r->date,
            'clicks' => (int) $r->clicks,
        ], $rows);
    }
}
