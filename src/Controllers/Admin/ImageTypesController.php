<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\ImageType;
use Ekanet\Support\ImageResizer;

final class ImageTypesController extends Controller
{
    public function index(): void
    {
        $this->render('admin/tipos_imagen/index.twig', [
            'page_title'  => 'Tipos de imagen',
            'active'      => 'tipos_imagen',
            'types'       => ImageType::all(),
            'gd_available'=> ImageResizer::isAvailable(),
        ]);
    }

    public function create(): void
    {
        $this->render('admin/tipos_imagen/form.twig', [
            'page_title' => 'Nuevo tipo de imagen',
            'active'     => 'tipos_imagen',
            'mode'       => 'create',
            'item'       => [
                'id_image_type' => 0,
                'name'          => '',
                'width'         => 250,
                'height'        => 250,
                'products'      => 1,
                'categories'    => 1,
                'manufacturers' => 1,
                'suppliers'     => 1,
                'stores'        => 1,
            ],
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/tipos_imagen/nuevo');
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, null);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/tipos_imagen/nuevo');
            return;
        }
        try {
            ImageType::create($data);
            Session::flash('success', "Tipo \"{$data['name']}\" creado. Regenera las miniaturas para aplicarlo.");
            $this->redirect($this->adminPath() . '/tipos_imagen');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al crear: ' . $e->getMessage());
            $this->redirect($this->adminPath() . '/tipos_imagen/nuevo');
        }
    }

    public function edit(string $id): void
    {
        $idInt = (int)$id;
        $item = ImageType::find($idInt);
        if (!$item) {
            Session::flash('error', 'Tipo de imagen no encontrado.');
            $this->redirect($this->adminPath() . '/tipos_imagen');
            return;
        }
        $this->render('admin/tipos_imagen/form.twig', [
            'page_title' => 'Editar tipo de imagen',
            'active'     => 'tipos_imagen',
            'mode'       => 'edit',
            'item'       => $item,
        ]);
    }

    public function update(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/tipos_imagen/{$idInt}/editar");
            return;
        }
        $data = $this->collect();
        $errors = $this->validate($data, $idInt);
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/tipos_imagen/{$idInt}/editar");
            return;
        }
        try {
            ImageType::update($idInt, $data);
            Session::flash('success', 'Tipo actualizado. Regenera las miniaturas para aplicar el nuevo tamaño.');
            $this->redirect($this->adminPath() . '/tipos_imagen');
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirect($this->adminPath() . "/tipos_imagen/{$idInt}/editar");
        }
    }

    public function destroy(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/tipos_imagen');
            return;
        }
        try {
            ImageType::delete($idInt);
            Session::flash('success', 'Tipo de imagen eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/tipos_imagen');
    }

    public function regenerate(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/tipos_imagen');
            return;
        }
        if (!ImageResizer::isAvailable()) {
            Session::flash('error', 'La extensión GD no está disponible en este servidor.');
            $this->redirect($this->adminPath() . '/tipos_imagen');
            return;
        }
        @set_time_limit(0);
        try {
            [$processed, $ok, $fail] = ImageResizer::regenerateAllProductThumbnails();
            $msg = "Regeneración completada · {$processed} imágenes procesadas · {$ok} miniaturas generadas";
            if ($fail > 0) $msg .= " · {$fail} fallidas (ver error log)";
            Session::flash('success', $msg);
        } catch (\Throwable $e) {
            Session::flash('error', 'Error en la regeneración: ' . $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/tipos_imagen');
    }

    private function collect(): array
    {
        return [
            'name'          => trim((string)$this->input('name', '')),
            'width'         => (int)$this->input('width', 0),
            'height'        => (int)$this->input('height', 0),
            'products'      => $this->input('products')      ? 1 : 0,
            'categories'    => $this->input('categories')    ? 1 : 0,
            'manufacturers' => $this->input('manufacturers') ? 1 : 0,
            'suppliers'     => $this->input('suppliers')     ? 1 : 0,
            'stores'        => $this->input('stores')        ? 1 : 0,
        ];
    }

    private function validate(array $data, ?int $excludeId): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'El nombre es obligatorio.';
        } elseif (!preg_match('/^[a-z0-9_]{2,64}$/', $data['name'])) {
            $errors[] = 'El nombre sólo admite minúsculas, dígitos y guion bajo (2–64 caracteres).';
        } elseif (ImageType::nameExists($data['name'], $excludeId)) {
            $errors[] = 'Ya existe un tipo con ese nombre.';
        }
        if ($data['width']  < 1 || $data['width']  > 4000) $errors[] = 'El ancho debe estar entre 1 y 4000 px.';
        if ($data['height'] < 1 || $data['height'] > 4000) $errors[] = 'El alto debe estar entre 1 y 4000 px.';
        return $errors;
    }
}
