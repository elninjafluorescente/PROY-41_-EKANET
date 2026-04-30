<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Database;
use Ekanet\Core\Session;
use Ekanet\Models\Tax;
use Ekanet\Models\TaxRulesGroup;

final class ImpuestosController extends Controller
{
    // ============ Tab 1 — Impuestos ============

    public function index(): void
    {
        $this->render('admin/impuestos/index.twig', [
            'page_title' => 'Impuestos',
            'active'     => 'impuestos',
            'taxes'      => Tax::all(),
        ]);
    }

    public function create(): void
    {
        $this->render('admin/impuestos/form.twig', [
            'page_title' => 'Nuevo impuesto',
            'active'     => 'impuestos',
            'mode'       => 'create',
            'item'       => ['id_tax' => 0, 'name' => '', 'rate' => '21.000', 'active' => 1],
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/impuestos/nuevo');
            return;
        }
        $data = $this->collectTax();
        $errors = $this->validateTax($data, null);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/impuestos/nuevo');
            return;
        }
        try {
            Tax::create($data);
            Session::flash('success', "Impuesto \"{$data['name']}\" creado.");
            $this->redirect($this->adminPath() . '/impuestos');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al crear: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/impuestos/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = Tax::find($idInt);
        if (!$item) {
            Session::flash('error', 'Impuesto no encontrado.');
            $this->redirect($this->adminPath() . '/impuestos');
            return;
        }
        $this->render('admin/impuestos/form.twig', [
            'page_title' => 'Editar impuesto',
            'active'     => 'impuestos',
            'mode'       => 'edit',
            'item'       => $item,
        ]);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/impuestos/{$idInt}/editar");
            return;
        }
        $data = $this->collectTax();
        $errors = $this->validateTax($data, $idInt);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/impuestos/{$idInt}/editar");
            return;
        }
        try {
            Tax::update($idInt, $data);
            Session::flash('success', 'Impuesto actualizado.');
            $this->redirect($this->adminPath() . '/impuestos');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/impuestos/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/impuestos');
            return;
        }
        if (Tax::isInUse($idInt)) {
            Session::flash('error', 'No se puede borrar: este impuesto está en uso por al menos una regla.');
            $this->redirect($this->adminPath() . '/impuestos');
            return;
        }
        try {
            Tax::softDelete($idInt);
            Session::flash('success', 'Impuesto eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/impuestos');
    }

    // ============ Tab 2 — Grupos de reglas ============

    public function groupsIndex(): void
    {
        $this->render('admin/impuestos/grupos.twig', [
            'page_title' => 'Grupos de reglas',
            'active'     => 'impuestos',
            'groups'     => TaxRulesGroup::all(),
        ]);
    }

    public function groupCreate(): void
    {
        $this->render('admin/impuestos/grupo_form.twig', [
            'page_title'  => 'Nuevo grupo de reglas',
            'active'      => 'impuestos',
            'mode'        => 'create',
            'item'        => ['id_tax_rules_group' => 0, 'name' => '', 'active' => 1],
            'rules'       => [],
            'taxes'       => Tax::all(),
            'countries'   => $this->countries(),
            'behaviors'   => TaxRulesGroup::BEHAVIOR_LABELS,
        ]);
    }

    public function groupStore(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/impuestos/grupos/nuevo');
            return;
        }
        $data = $this->collectGroup();
        $errors = $this->validateGroup($data, null);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/impuestos/grupos/nuevo');
            return;
        }
        try {
            $id = TaxRulesGroup::create($data);
            Session::flash('success', "Grupo \"{$data['name']}\" creado. Añade ahora las reglas.");
            $this->redirect($this->adminPath() . "/impuestos/grupos/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al crear: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/impuestos/grupos/nuevo');
        }
    }

    public function groupEdit(string $id): void
    {
        $idInt = (int)$id;
        $item = TaxRulesGroup::find($idInt);
        if (!$item) {
            Session::flash('error', 'Grupo no encontrado.');
            $this->redirect($this->adminPath() . '/impuestos/grupos');
            return;
        }
        $this->render('admin/impuestos/grupo_form.twig', [
            'page_title' => 'Editar grupo de reglas',
            'active'     => 'impuestos',
            'mode'       => 'edit',
            'item'       => $item,
            'rules'      => TaxRulesGroup::rules($idInt),
            'taxes'      => Tax::all(),
            'countries'  => $this->countries(),
            'behaviors'  => TaxRulesGroup::BEHAVIOR_LABELS,
        ]);
    }

    public function groupUpdate(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/impuestos/grupos/{$idInt}/editar");
            return;
        }
        $data = $this->collectGroup();
        $errors = $this->validateGroup($data, $idInt);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/impuestos/grupos/{$idInt}/editar");
            return;
        }
        try {
            TaxRulesGroup::update($idInt, $data);
            Session::flash('success', 'Grupo actualizado.');
            $this->redirect($this->adminPath() . "/impuestos/grupos/{$idInt}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/impuestos/grupos/{$idInt}/editar");
        }
    }

    public function groupDestroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/impuestos/grupos');
            return;
        }
        if (TaxRulesGroup::isInUse($idInt)) {
            Session::flash('error', 'No se puede borrar: hay productos asignados a este grupo. Reasígnalos antes.');
            $this->redirect($this->adminPath() . '/impuestos/grupos');
            return;
        }
        try {
            TaxRulesGroup::softDelete($idInt);
            Session::flash('success', 'Grupo eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/impuestos/grupos');
    }

    // ============ Tab 2.b — Reglas dentro de un grupo ============

    public function ruleAdd(string $id): void
    {
        $idGroup = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/impuestos/grupos/{$idGroup}/editar");
            return;
        }
        $data = $this->collectRule();
        $errors = $this->validateRule($data);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/impuestos/grupos/{$idGroup}/editar");
            return;
        }
        try {
            TaxRulesGroup::addRule($idGroup, $data);
            Session::flash('success', 'Regla añadida.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al añadir regla: ' . $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/impuestos/grupos/{$idGroup}/editar");
    }

    public function ruleDestroy(string $id, string $ruleId): void
    {
        $idGroup = (int)$id;
        $idRule  = (int)$ruleId;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/impuestos/grupos/{$idGroup}/editar");
            return;
        }
        try {
            TaxRulesGroup::deleteRule($idRule);
            Session::flash('success', 'Regla eliminada.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/impuestos/grupos/{$idGroup}/editar");
    }

    // ============ Helpers ============

    private function collectTax(): array
    {
        return [
            'name'   => trim((string)$this->input('name', '')),
            'rate'   => (string)$this->input('rate', '0'),
            'active' => $this->input('active') ? 1 : 0,
        ];
    }

    private function validateTax(array $data, ?int $excludeId): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($data['name']) > 32) {
            $errors[] = 'El nombre no puede superar 32 caracteres.';
        } elseif (Tax::nameExists($data['name'], $excludeId)) {
            $errors[] = 'Ya existe un impuesto con ese nombre.';
        }
        $rate = (float)str_replace(',', '.', $data['rate']);
        if ($rate < 0 || $rate > 100) {
            $errors[] = 'La tasa debe estar entre 0 y 100.';
        }
        return $errors;
    }

    private function collectGroup(): array
    {
        return [
            'name'   => trim((string)$this->input('name', '')),
            'active' => $this->input('active') ? 1 : 0,
        ];
    }

    private function validateGroup(array $data, ?int $excludeId): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'El nombre del grupo es obligatorio.';
        } elseif (mb_strlen($data['name']) > 50) {
            $errors[] = 'El nombre no puede superar 50 caracteres.';
        } elseif (TaxRulesGroup::nameExists($data['name'], $excludeId)) {
            $errors[] = 'Ya existe un grupo con ese nombre.';
        }
        return $errors;
    }

    private function collectRule(): array
    {
        return [
            'id_country'   => (int)$this->input('id_country', 0),
            'id_state'     => (int)$this->input('id_state', 0),
            'zipcode_from' => trim((string)$this->input('zipcode_from', '')),
            'zipcode_to'   => trim((string)$this->input('zipcode_to', '')),
            'id_tax'       => (int)$this->input('id_tax', 0),
            'behavior'     => (int)$this->input('behavior', 0),
            'description'  => trim((string)$this->input('description', '')),
        ];
    }

    private function validateRule(array $data): array
    {
        $errors = [];
        if ($data['id_country'] <= 0) $errors[] = 'Selecciona un país.';
        if ($data['id_tax']     <= 0) $errors[] = 'Selecciona un impuesto.';
        return $errors;
    }

    /** Lista mínima de países activos (id + nombre) para selectores. */
    private function countries(int $idLang = 1): array
    {
        return Database::run(
            'SELECT c.id_country, cl.name
             FROM `{P}country` c
             LEFT JOIN `{P}country_lang` cl ON cl.id_country = c.id_country AND cl.id_lang = :l
             WHERE c.active = 1
             ORDER BY cl.name',
            ['l' => $idLang]
        )->fetchAll();
    }
}
