<?php
declare(strict_types=1);

namespace Ekanet\Controllers\Admin;

use Ekanet\Core\Auth;
use Ekanet\Core\Controller;
use Ekanet\Core\Csrf;
use Ekanet\Core\Session;
use Ekanet\Models\NewsletterCampaign;
use Ekanet\Models\NewsletterSubscriber;

final class NewsletterController extends Controller
{
    private const PER_PAGE = 50;

    // ============ Tab 1 — Suscriptores ============

    public function subscribersIndex(): void
    {
        $page   = max(1, (int)$this->input('p', 1));
        $search = trim((string)$this->input('q', ''));
        $offset = ($page - 1) * self::PER_PAGE;
        $total  = NewsletterSubscriber::count($search);

        $this->render('admin/newsletter/subscribers_index.twig', [
            'page_title' => 'Newsletter — Suscriptores',
            'active'     => 'newsletter',
            'subscribers'=> NewsletterSubscriber::all(self::PER_PAGE, $offset, $search),
            'customer_count' => NewsletterSubscriber::customerCount(),
            'total' => $total,
            'pages' => (int)ceil($total / self::PER_PAGE),
            'page'  => $page,
            'search'=> $search,
        ]);
    }

    public function subscriberCreate(): void
    {
        $this->render('admin/newsletter/subscriber_form.twig', [
            'page_title' => 'Nuevo suscriptor',
            'active'     => 'newsletter',
            'mode'       => 'create',
            'item'       => ['id' => 0, 'email' => '', 'name' => '', 'active' => 1],
        ]);
    }

    public function subscriberStore(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/newsletter/suscriptores/nuevo');
            return;
        }
        $email = trim((string)$this->input('email', ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Email no válido.');
            $this->redirect($this->adminPath() . '/newsletter/suscriptores/nuevo');
            return;
        }
        if (NewsletterSubscriber::emailExists($email)) {
            Session::flash('error', 'Este email ya está suscrito.');
            $this->redirect($this->adminPath() . '/newsletter/suscriptores/nuevo');
            return;
        }
        try {
            NewsletterSubscriber::create([
                'email'  => $email,
                'name'   => (string)$this->input('name', ''),
                'active' => $this->input('active') ? 1 : 0,
            ]);
            Session::flash('success', 'Suscriptor añadido.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/newsletter/suscriptores');
    }

    public function subscriberEdit(string $id): void
    {
        $idInt = (int)$id;
        $item = NewsletterSubscriber::find($idInt);
        if (!$item) {
            Session::flash('error', 'Suscriptor no encontrado.');
            $this->redirect($this->adminPath() . '/newsletter/suscriptores');
            return;
        }
        $this->render('admin/newsletter/subscriber_form.twig', [
            'page_title' => 'Editar suscriptor',
            'active'     => 'newsletter',
            'mode'       => 'edit',
            'item'       => $item,
        ]);
    }

    public function subscriberUpdate(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/newsletter/suscriptores/{$idInt}/editar");
            return;
        }
        $email = trim((string)$this->input('email', ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Email no válido.');
            $this->redirect($this->adminPath() . "/newsletter/suscriptores/{$idInt}/editar");
            return;
        }
        if (NewsletterSubscriber::emailExists($email, $idInt)) {
            Session::flash('error', 'Otro suscriptor ya usa ese email.');
            $this->redirect($this->adminPath() . "/newsletter/suscriptores/{$idInt}/editar");
            return;
        }
        try {
            NewsletterSubscriber::update($idInt, [
                'email'  => $email,
                'name'   => (string)$this->input('name', ''),
                'active' => $this->input('active') ? 1 : 0,
            ]);
            Session::flash('success', 'Suscriptor actualizado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/newsletter/suscriptores');
    }

    public function subscriberDestroy(string $id): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/newsletter/suscriptores');
            return;
        }
        try {
            NewsletterSubscriber::delete((int)$id);
            Session::flash('success', 'Suscriptor eliminado.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/newsletter/suscriptores');
    }

    public function subscribersImport(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/newsletter/suscriptores');
            return;
        }
        $raw = (string)$this->input('emails', '');
        if (trim($raw) === '') {
            Session::flash('error', 'Pega al menos un email.');
            $this->redirect($this->adminPath() . '/newsletter/suscriptores');
            return;
        }
        try {
            [$ok, $dup, $bad] = NewsletterSubscriber::importBulk($raw);
            Session::flash('success', "Importados: {$ok} · Duplicados: {$dup} · Inválidos: {$bad}");
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/newsletter/suscriptores');
    }

    // ============ Tab 2 — Campañas ============

    public function campaignsIndex(): void
    {
        $page   = max(1, (int)$this->input('p', 1));
        $offset = ($page - 1) * self::PER_PAGE;
        $total  = NewsletterCampaign::count();

        $this->render('admin/newsletter/campaigns_index.twig', [
            'page_title' => 'Newsletter — Campañas',
            'active'     => 'newsletter',
            'campaigns'  => NewsletterCampaign::all(self::PER_PAGE, $offset),
            'statuses'   => NewsletterCampaign::STATUSES,
            'badges'     => NewsletterCampaign::STATUS_BADGES,
            'total' => $total,
            'pages' => (int)ceil($total / self::PER_PAGE),
            'page'  => $page,
        ]);
    }

    public function campaignCreate(): void
    {
        $this->render('admin/newsletter/campaign_form.twig', [
            'page_title' => 'Nueva campaña',
            'active'     => 'newsletter',
            'mode'       => 'create',
            'item'       => ['id_campaign' => 0, 'subject' => '', 'body_html' => '', 'target' => 'all', 'status' => 'draft'],
            'targets'    => NewsletterSubscriber::TARGETS,
            'statuses'   => NewsletterCampaign::STATUSES,
            'badges'     => NewsletterCampaign::STATUS_BADGES,
        ]);
    }

    public function campaignStore(): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/newsletter/campanas/nueva');
            return;
        }
        $errors = $this->validateCampaign();
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . '/newsletter/campanas/nueva');
            return;
        }
        try {
            $id = NewsletterCampaign::create($this->collectCampaign(), (int)(Auth::user()['id'] ?? 0));
            Session::flash('success', 'Campaña creada como borrador.');
            $this->redirect($this->adminPath() . "/newsletter/campanas/{$id}/editar");
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect($this->adminPath() . '/newsletter/campanas/nueva');
        }
    }

    public function campaignEdit(string $id): void
    {
        $idInt = (int)$id;
        $item = NewsletterCampaign::find($idInt);
        if (!$item) {
            Session::flash('error', 'Campaña no encontrada.');
            $this->redirect($this->adminPath() . '/newsletter/campanas');
            return;
        }
        $this->render('admin/newsletter/campaign_form.twig', [
            'page_title' => 'Editar campaña',
            'active'     => 'newsletter',
            'mode'       => 'edit',
            'item'       => $item,
            'targets'    => NewsletterSubscriber::TARGETS,
            'statuses'   => NewsletterCampaign::STATUSES,
            'badges'     => NewsletterCampaign::STATUS_BADGES,
            'logs'       => NewsletterCampaign::logs($idInt, 50),
        ]);
    }

    public function campaignUpdate(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/newsletter/campanas/{$idInt}/editar");
            return;
        }
        $errors = $this->validateCampaign();
        if ($errors) {
            Session::flash('error', implode(' · ', $errors));
            $this->redirect($this->adminPath() . "/newsletter/campanas/{$idInt}/editar");
            return;
        }
        try {
            NewsletterCampaign::update($idInt, $this->collectCampaign());
            Session::flash('success', 'Campaña actualizada.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/newsletter/campanas/{$idInt}/editar");
    }

    public function campaignDestroy(string $id): void
    {
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . '/newsletter/campanas');
            return;
        }
        try {
            NewsletterCampaign::delete((int)$id);
            Session::flash('success', 'Campaña eliminada.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($this->adminPath() . '/newsletter/campanas');
    }

    public function campaignSend(string $id): void
    {
        $idInt = (int)$id;
        if (!Csrf::check((string)$this->input('_csrf', ''))) {
            Session::flash('error', 'Token CSRF inválido.');
            $this->redirect($this->adminPath() . "/newsletter/campanas/{$idInt}/editar");
            return;
        }
        @set_time_limit(0);
        try {
            [$sent, $failed, $total] = NewsletterCampaign::send($idInt);
            $msg = "Envío completado · {$sent} OK · {$failed} fallidos · {$total} totales";
            Session::flash($failed > 0 && $sent === 0 ? 'error' : 'success', $msg);
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al enviar: ' . $e->getMessage());
        }
        $this->redirect($this->adminPath() . "/newsletter/campanas/{$idInt}/editar");
    }

    // ============ Helpers ============

    private function collectCampaign(): array
    {
        return [
            'subject'   => (string)$this->input('subject', ''),
            'body_html' => (string)$this->input('body_html', ''),
            'target'    => (string)$this->input('target', 'all'),
        ];
    }

    private function validateCampaign(): array
    {
        $errors = [];
        $subject = trim((string)$this->input('subject', ''));
        $body    = trim((string)$this->input('body_html', ''));
        if ($subject === '') $errors[] = 'El asunto es obligatorio.';
        if (mb_strlen($subject) > 255) $errors[] = 'El asunto no puede superar 255 caracteres.';
        if ($body === '') $errors[] = 'El cuerpo HTML no puede estar vacío.';
        $target = (string)$this->input('target', 'all');
        if (!isset(NewsletterSubscriber::TARGETS[$target])) $errors[] = 'Target no válido.';
        return $errors;
    }
}
