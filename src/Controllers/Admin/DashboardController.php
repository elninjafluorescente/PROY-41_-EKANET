<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Database;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $metrics = [
            'total_products'  => $this->safeCount('product'),
            'total_customers' => $this->safeCount('customer'),
            'total_orders'    => $this->safeCount('orders'),
            'total_employees' => $this->safeCount('employee'),
        ];

        $this->render('admin/dashboard/index.twig', [
            'page_title' => 'Dashboard',
            'active'     => 'dashboard',
            'metrics'    => $metrics,
        ]);
    }

    private function safeCount(string $table): int
    {
        try {
            $stmt = Database::run('SELECT COUNT(*) AS c FROM `{P}' . $table . '`');
            return (int)($stmt->fetch()['c'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
