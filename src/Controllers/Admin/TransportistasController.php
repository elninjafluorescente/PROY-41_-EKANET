<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\Carrier;
use Ekanet\Models\Delivery;
use Ekanet\Models\Zone;

final class TransportistasController extends Controller
{
    public function index(): void
    {
        $this->render('admin/transportistas/index.twig', [
            'page_title' => 'Transportistas',
            'active'     => 'transportistas',
            'carriers'   => Carrier::all(),
            'methods'    => Carrier::SHIPPING_METHODS,
        ]);
    }

    public function create(): void
    {
        $this->renderForm('create', [
            'id_carrier'      => 0,
            'name'            => '',
            'url'             => '',
            'delay'           => '',
            'shipping_method' => 0,
            'is_free'         => 0,
            'active'          => 1,
            'grade'           => 0,
            'max_width'       => 0,
            'max_height'      => 0,
            'max_depth'       => 0,
            'max_weight'      => '0',
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/transportistas/nuevo');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/transportistas/nuevo');
            return;
        }
        try {
            $id = Carrier::create($data);
            // Persistir zonas asignadas (la matriz de tarifas se edita al volver al form)
            Delivery::setCarrierZones($id, $this->collectZoneIds());
            Session::flash('success', "Transportista \"{$data['name']}\" creado. Configura ahora las tarifas.");
            $this->redirect($this->adminPath() . "/transportistas/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/transportistas/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = Carrier::find($idInt);
        if (!$item) {
            Session::flash('error', 'Transportista no encontrado.');
            $this->redirect($this->adminPath() . '/transportistas');
            return;
        }
        $this->renderForm('edit', $item);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/transportistas/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/transportistas/{$idInt}/editar");
            return;
        }
        try {
            Carrier::update($idInt, $data);
            Delivery::setCarrierZones($idInt, $this->collectZoneIds());

            // Persistir matriz de tarifas si se envió (sólo si shipping_method tiene rangos)
            $rangeType = Delivery::rangeTypeForMethod((int)$data['shipping_method']);
            if ($rangeType !== null) {
                $prices = $this->input('prices', []);
                if (is_array($prices)) {
                    Delivery::setPriceMatrix($idInt, $rangeType, $prices);
                }
            }

            Session::flash('success', 'Transportista actualizado.');
            $this->redirect($this->adminPath() . "/transportistas/{$idInt}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/transportistas/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/transportistas');
            return;
        }
        try {
            Carrier::delete($idInt);
            Delivery::purgeForCarrier($idInt);
            Session::flash('success', 'Transportista eliminado (soft-delete) y tarifas asociadas borradas.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/transportistas');
    }

    public function addRange(string $id): void
    {
        $idCarrier = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/transportistas/{$idCarrier}/editar");
            return;
        }
        $type = (string)$this->input('range_type', '');
        if (!in_array($type, [Delivery::RANGE_TYPE_PRICE, Delivery::RANGE_TYPE_WEIGHT], true)) {
            Session::flash('error', 'Tipo de rango no válido.');
            $this->redirect($this->adminPath() . "/transportistas/{$idCarrier}/editar");
            return;
        }
        $from = (float)str_replace(',', '.', (string)$this->input('range_from', '0'));
        $to   = (float)str_replace(',', '.', (string)$this->input('range_to',   '0'));
        if ($to <= $from) {
            Session::flash('error', 'El "hasta" del rango debe ser mayor que el "desde".');
            $this->redirect($this->adminPath() . "/transportistas/{$idCarrier}/editar");
            return;
        }
        try {
            Delivery::addRange($idCarrier, $type, $from, $to);
            Session::flash('success', 'Rango añadido.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al añadir rango: ' . $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/transportistas/{$idCarrier}/editar");
    }

    public function deleteRange(string $id, string $type, string $rangeId): void
    {
        $idCarrier = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/transportistas/{$idCarrier}/editar");
            return;
        }
        if (!in_array($type, [Delivery::RANGE_TYPE_PRICE, Delivery::RANGE_TYPE_WEIGHT], true)) {
            Session::flash('error', 'Tipo de rango no válido.');
            $this->redirect($this->adminPath() . "/transportistas/{$idCarrier}/editar");
            return;
        }
        try {
            Delivery::deleteRange($idCarrier, $type, (int)$rangeId);
            Session::flash('success', 'Rango eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/transportistas/{$idCarrier}/editar");
    }

    private function renderForm(string $mode, array $item): void
    {
        $idCarrier = (int)($item['id_carrier'] ?? 0);
        $shippingMethod = (int)($item['shipping_method'] ?? 0);
        $rangeType = Delivery::rangeTypeForMethod($shippingMethod);

        $this->render('admin/transportistas/form.twig', [
            'page_title'       => $mode === 'create' ? 'Nuevo transportista' : 'Editar transportista',
            'active'           => 'transportistas',
            'mode'             => $mode,
            'item'             => $item,
            'methods'          => Carrier::SHIPPING_METHODS,
            'all_zones'        => Zone::all(true),
            'carrier_zones'    => $idCarrier > 0 ? Delivery::zonesForCarrier($idCarrier) : [],
            'range_type'       => $rangeType,
            'ranges'           => ($idCarrier > 0 && $rangeType) ? Delivery::rangesForCarrier($idCarrier, $rangeType) : [],
            'price_matrix'     => ($idCarrier > 0 && $rangeType) ? Delivery::priceMatrix($idCarrier, $rangeType) : [],
        ]);
    }

    /** Recoge los IDs de zona seleccionados en el form (checkboxes name="zones[]"). */
    private function collectZoneIds(): array
    {
        $raw = $_POST['zones'] ?? [];
        if (!is_array($raw)) return [];
        return array_values(array_filter(array_map('intval', $raw), fn($z) => $z > 0));
    }

    private function collect(): array
    {
        return [
            'name'            => trim((string)$this->input('name', '')),
            'url'             => trim((string)$this->input('url', '')),
            'delay'           => trim((string)$this->input('delay', '')),
            'shipping_method' => (int)$this->input('shipping_method', 0),
            'is_free'         => $this->input('is_free') ? 1 : 0,
            'active'          => $this->input('active') ? 1 : 0,
            'grade'           => (int)$this->input('grade', 0),
            'max_width'       => (int)$this->input('max_width', 0),
            'max_height'      => (int)$this->input('max_height', 0),
            'max_depth'       => (int)$this->input('max_depth', 0),
            'max_weight'      => (string)$this->input('max_weight', '0'),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($data['name']) > 64) {
            $errors[] = 'El nombre es demasiado largo.';
        }
        if ($data['url'] !== '' && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'La URL de seguimiento no es válida.';
        }
        return $errors;
    }
}
