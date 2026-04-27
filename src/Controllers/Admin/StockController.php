<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Database;
use Ekanet\Core\Session;
use Ekanet\Models\StockAvailable;

final class StockController extends Controller
{
    private const PER_PAGE = 100;

    public function index(): void
    {
        $page    = max(1, (int)$this->input('p', 1));
        $search  = trim((string)$this->input('q', ''));
        $lowOnly = (bool)$this->input('low', 0);
        $offset  = ($page - 1) * self::PER_PAGE;

        $total = StockAvailable::countProducts($search, $lowOnly);
        $rows  = StockAvailable::listProducts(1, 1, self::PER_PAGE, $offset, $search, $lowOnly);

        // Stats globales (totales sin filtro)
        $stats = $this->stats();

        $this->render('admin/stock/index.twig', [
            'page_title' => 'Stock',
            'active'     => 'stock',
            'rows'       => $rows,
            'total'      => $total,
            'pages'      => (int)ceil($total / self::PER_PAGE),
            'page'       => $page,
            'search'     => $search,
            'low_only'   => $lowOnly,
            'stats'      => $stats,
        ]);
    }

    public function bulkUpdate(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/stock');
            return;
        }

        $stockMap     = $_POST['stock']     ?? [];
        $thresholdMap = $_POST['threshold'] ?? [];
        if (!is_array($stockMap)) $stockMap = [];
        if (!is_array($thresholdMap)) $thresholdMap = [];

        $updated = 0;
        try {
            foreach ($stockMap as $idProduct => $qty) {
                $idProduct = (int)$idProduct;
                if ($idProduct < 1) continue;
                $newQty = max(0, (int)$qty);

                $current = StockAvailable::getQuantity($idProduct, 0, 1);
                if ($current !== $newQty) {
                    StockAvailable::setQuantity($idProduct, $newQty, 0, 1);
                    // Mantener ps_product.quantity sincronizado
                    Database::run(
                        'UPDATE `{P}product` SET quantity = :q, date_upd = NOW() WHERE id_product = :id',
                        ['q' => $newQty, 'id' => $idProduct]
                    );
                    $updated++;
                }
            }
            foreach ($thresholdMap as $idProduct => $threshold) {
                $idProduct = (int)$idProduct;
                if ($idProduct < 1) continue;
                $value = $threshold === '' ? null : max(0, (int)$threshold);
                Database::run(
                    'UPDATE `{P}product` SET low_stock_threshold = :t,
                       low_stock_alert = :a, date_upd = NOW()
                     WHERE id_product = :id',
                    [
                        't'  => $value,
                        'a'  => $value !== null ? 1 : 0,
                        'id' => $idProduct,
                    ]
                );
            }
            Session::flash('success', "Actualizadas {$updated} cantidades de stock.");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al guardar: ' . $e->getMessage());
        }

        // Conservar filtros activos
        $qs = http_build_query(array_filter([
            'p'   => (int)$this->input('p', 0),
            'q'   => $this->input('q', ''),
            'low' => $this->input('low', 0) ? 1 : null,
        ]));
        $this->redirect($this->adminPath() . '/stock' . ($qs ? '?' . $qs : ''));
    }

    /**
     * Métricas globales: total productos, agotados, bajos, OK, valor inventario.
     */
    private function stats(): array
    {
        $sql = "SELECT
                  COUNT(*) AS total,
                  SUM(CASE WHEN COALESCE(sa.quantity,0) <= 0 THEN 1 ELSE 0 END) AS empty_,
                  SUM(CASE WHEN COALESCE(sa.quantity,0) > 0
                           AND COALESCE(sa.quantity,0) <= COALESCE(p.low_stock_threshold, 5) THEN 1 ELSE 0 END) AS low_,
                  SUM(CASE WHEN COALESCE(sa.quantity,0) > COALESCE(p.low_stock_threshold, 5) THEN 1 ELSE 0 END) AS ok_,
                  COALESCE(SUM(COALESCE(sa.quantity,0) * p.wholesale_price), 0) AS value_
                FROM `{P}product` p
                LEFT JOIN `{P}stock_available` sa
                  ON sa.id_product = p.id_product AND sa.id_product_attribute = 0 AND sa.id_shop = 1";
        $row = Database::run($sql)->fetch();
        return [
            'total'  => (int)($row['total']  ?? 0),
            'empty'  => (int)($row['empty_'] ?? 0),
            'low'    => (int)($row['low_']   ?? 0),
            'ok'     => (int)($row['ok_']    ?? 0),
            'value'  => (float)($row['value_'] ?? 0),
        ];
    }
}
