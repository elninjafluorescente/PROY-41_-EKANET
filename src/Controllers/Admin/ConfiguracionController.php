<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Configuration;

final class ConfiguracionController extends Controller
{
    /** Campos de texto sencillos. */
    private const TEXT_FIELDS = [
        'PS_SHOP_NAME', 'PS_SHOP_EMAIL', 'PS_SHOP_PHONE', 'PS_SHOP_DETAILS',
        'PS_SHOP_ADDR1', 'PS_SHOP_ADDR2', 'PS_SHOP_CODE', 'PS_SHOP_CITY',
        'PS_SHIPPING_FREE_PRICE', 'PS_SHIPPING_HANDLING', 'PS_TAX_DEFAULT',
        'PS_INVOICE_PREFIX', 'PS_DELIVERY_PREFIX', 'PS_CREDIT_SLIP_PREFIX',
        'PS_MAINTENANCE_TEXT', 'PS_MAINTENANCE_IP',
        'PS_COOKIE_BANNER_TEXT', 'PS_GDPR_PRIVACY_URL',
    ];

    /** Campos booleanos (checkboxes). */
    private const BOOL_FIELDS = ['PS_SHOP_ENABLE', 'PS_TAX'];

    public function index(): void
    {
        $allKeys = array_merge(self::TEXT_FIELDS, self::BOOL_FIELDS);
        $config  = Configuration::getMany($allKeys);

        $this->render('admin/configuracion/index.twig', [
            'page_title' => 'Configuración',
            'active'     => 'configuracion',
            'config'     => $config,
        ]);
    }

    public function update(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/configuracion');
            return;
        }

        // Validación mínima
        $email = trim((string)$this->input('PS_SHOP_EMAIL', ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'El email de la tienda no es válido.');
            $this->redirect($this->adminPath() . '/configuracion');
            return;
        }

        $data = [];
        foreach (self::TEXT_FIELDS as $key) {
            $data[$key] = trim((string)$this->input($key, ''));
        }
        foreach (self::BOOL_FIELDS as $key) {
            $data[$key] = $this->input($key) ? '1' : '0';
        }

        try {
            Configuration::setMany($data);
            Session::flash('success', 'Configuración guardada.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al guardar: ' . $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/configuracion');
    }
}
