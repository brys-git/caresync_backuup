<?php

namespace App\Controllers;

use App\Services\ActivityLogService;
use App\Services\ClientDetailService;
use App\Services\LegacyRecordService;
use App\Services\ReportPdfService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The one "Client details" page for System Admin, Branch Admin and Staff.
 *
 * The three roles reach it through their own URLs (and menus) but run the
 * same code and render the same view, so the information can never differ.
 * What differs is only which buttons are offered - see actions().
 *
 * Access is enforced here (ClientDetailService::find): System Admin sees all
 * branches; Branch Admin and Staff only their own. Another branch's client is
 * answered exactly like a client that does not exist (Not Found). The route
 * filters (role:1/2/3) already keep every other role out. Collectors and
 * plan holders have their own screens.
 */
class ClientDetailController extends BaseController
{
    private ClientDetailService $service;

    public function __construct()
    {
        $this->service = new ClientDetailService();
    }

    // ------------------------------------------------------------------

    public function show(int $id): string
    {
        $client = $this->clientOr404($id);
        $role   = (int) session('role_id');
        $cfg    = $this->roleConfig($role);

        $data = $this->service->page($client, $this->paymentFilters());

        $data['role_layout'] = $cfg['layout'];
        $data['show_page_head'] = false; // the shared partial draws its own header
        $data['page_title']  = $this->fullName($client);
        $data['page_sub']    = 'Plan holder #' . (string) ($client['unique_identifier'] ?: $client['plan_holder_id']) . ' · ' . ($client['branch_name'] ?: 'No branch');
        $data['crumbs']      = [
            ['label' => 'Plan holders', 'url' => site_url($cfg['list']), ],
            ['label' => $this->fullName($client)],
        ];
        $data['actions']       = $this->actions($client, $cfg, $data);
        $data['urls']          = $cfg + [
            'reinstate' => site_url($cfg['reinstate'] . $id),
            'note'      => site_url($cfg['note'] . $id),
            'statement' => site_url($cfg['statement'] . $id),
            'self'      => site_url($cfg['view'] . $id),
            'file'      => site_url($cfg['file'] . $id . '/'),
        ];
        $data['tab']           = in_array((string) $this->request->getGet('tab'), ['overview', 'payments', 'services', 'beneficiaries', 'documents', 'activity'], true)
            ? (string) $this->request->getGet('tab')
            : 'overview';
        $data['approval']      = $role === 2 ? $this->approval($data) : null;
        $data['role_id']       = $role;

        return view('client_detail/show', $data);
    }

    /** Printable / downloadable statement (existing PDF report header). */
    public function statement(int $id): ResponseInterface
    {
        $client = $this->clientOr404($id);
        $filters = $this->paymentFilters();
        $data = $this->service->statementRows($client, $filters);

        $lines = [];
        $f = $data['payments']['filters'];
        if ($f['status'] !== '') {
            $lines[] = 'Status: ' . ucfirst($f['status']);
        }
        if ($f['method'] !== '') {
            $lines[] = 'Method: ' . (ClientDetailService::METHODS[$f['method']] ?? $f['method']);
        }
        if ($f['or_no'] !== '') {
            $lines[] = 'OR number contains: ' . $f['or_no'];
        }

        $data['client'] = $client;
        $data['client_name'] = $this->fullName($client);

        (new ActivityLogService())->log((int) session('user_id'), 'viewed', 'plan_holder', (int) $id, 'Printed account statement');

        return (new ReportPdfService())->respond(
            $this->response,
            'client_statement',
            $data,
            [
                'branch_name'  => (string) $client['branch_name'],
                'date_from'    => $f['date_from'] !== '' ? $f['date_from'] : null,
                'date_to'      => $f['date_to'] !== '' ? $f['date_to'] : null,
                'filter_lines' => $lines,
            ],
            ! $this->request->getGet('download')
        );
    }

    /** Saves the internal note (plan_holders.remarks - staff-only, never shown to the client). */
    public function saveNote(int $id)
    {
        $client = $this->clientOr404($id);
        $cfg    = $this->roleConfig((int) session('role_id'));

        $note = trim((string) $this->request->getPost('remarks'));
        if (mb_strlen($note) > 1000) {
            return redirect()->to(site_url($cfg['view'] . $id) . '?tab=overview')->with('error', 'A note can be up to 1,000 characters.');
        }

        db_connect()->table('plan_holders')
            ->where('plan_holder_id', (int) $client['plan_holder_id'])
            ->update(['remarks' => $note !== '' ? $note : null]);

        (new ActivityLogService())->log((int) session('user_id'), 'updated', 'plan_holder', (int) $client['plan_holder_id'], 'Updated the internal note');

        return redirect()->to(site_url($cfg['view'] . $id) . '?tab=overview')->with('success', 'Note saved.');
    }

    /**
     * Serves a stored file belonging to THIS client (WRITEPATH is not
     * web-accessible): id | legacy | proof | claim.
     */
    public function file(int $id, string $kind, int $docId): ResponseInterface
    {
        $client = $this->clientOr404($id);
        $db     = db_connect();
        $path   = null;
        $name   = 'file';
        $mime   = '';

        switch ($kind) {
            case 'id':
                $row = $db->table('government_id_verifications')->where('verification_id', $docId)->where('user_id', (int) $client['user_id'])->get()->getRowArray();
                if ($row && ! empty($row['file_path'])) {
                    $path = WRITEPATH . $row['file_path'];
                    $name = (string) $row['original_name'];
                    $mime = (string) $row['mime_type'];
                }
                break;

            case 'legacy':
                if (LegacyRecordService::ready()) {
                    $row = $db->table('legacy_documents')->where('legacy_document_id', $docId)->where('plan_holder_id', (int) $client['plan_holder_id'])->get()->getRowArray();
                    if ($row) {
                        $path = (new LegacyRecordService())->documentPath($row);
                        $name = (string) $row['original_name'];
                        $mime = (string) $row['mime_type'];
                    }
                }
                break;

            case 'proof':
                $row = $db->table('payments pay')
                    ->select('pay.proof_image')
                    ->join('plans p', 'p.plan_id = pay.plan_id', 'inner')
                    ->where('pay.payment_id', $docId)
                    ->where('p.plan_holder_id', (int) $client['plan_holder_id'])
                    ->get()->getRowArray();
                if ($row && ! empty($row['proof_image'])) {
                    $path = WRITEPATH . 'uploads/payment-proofs/' . basename((string) $row['proof_image']);
                    $name = basename((string) $row['proof_image']);
                }
                break;

            case 'claim':
                $row = $db->table('service_application_documents d')
                    ->select('d.path, d.original_name, d.mime_type')
                    ->join('service_applications sa', 'sa.application_id = d.application_id', 'inner')
                    ->where('d.document_id', $docId)
                    ->where('sa.plan_holder_id', (int) $client['plan_holder_id'])
                    ->get()->getRowArray();
                if ($row) {
                    $path = WRITEPATH . $row['path'];
                    $name = (string) $row['original_name'];
                    $mime = (string) $row['mime_type'];
                }
                break;
        }

        if ($path === null || ! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($mime === '') {
            $detected = @mime_content_type($path);
            $mime = is_string($detected) ? $detected : 'application/octet-stream';
        }

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', ($this->request->getGet('download') ? 'attachment' : 'inline') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name) . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody((string) file_get_contents($path));
    }

    // ------------------------------------------------------------------

    private function clientOr404(int $id): array
    {
        $client = $this->service->find($id, (int) session('role_id'), (int) session('branch_id'));

        if ($client === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $client;
    }

    private function fullName(array $client): string
    {
        return trim(preg_replace('/\s+/', ' ', $client['first_name'] . ' ' . $client['middle_name'] . ' ' . $client['last_name'] . ' ' . ($client['name_extension'] ?? '')));
    }

    /** @return array<string,mixed> */
    private function paymentFilters(): array
    {
        $date = static fn ($v): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '';
        $in   = static fn ($v, array $allowed): string => in_array((string) $v, $allowed, true) ? (string) $v : '';

        return [
            'date_from' => $date($this->request->getGet('pf_from')),
            'date_to'   => $date($this->request->getGet('pf_to')),
            'status'    => $in($this->request->getGet('pf_status'), ['verified', 'pending', 'rejected']),
            'method'    => $in($this->request->getGet('pf_method'), array_keys(ClientDetailService::METHODS)),
            'or_no'     => mb_substr(trim((string) $this->request->getGet('pf_or')), 0, 60),
            'page'      => (int) $this->request->getGet('pp'),
        ];
    }

    /**
     * Where each role's links go. Buttons a role does not have are null and
     * are not drawn; the routes behind them are role-filtered as well.
     *
     * @return array<string,mixed>
     */
    private function roleConfig(int $role): array
    {
        return match ($role) {
            1 => [
                'layout' => 'layouts/admin', 'list' => 'admin/client-management', 'view' => 'admin/client-management/view/',
                'edit' => 'admin/client-management/edit/', 'resend' => 'admin/client-management/resend-login/',
                'reinstate' => 'admin/client-management/reinstate/', 'note' => 'admin/client-management/note/',
                'statement' => 'admin/client-management/statement/', 'file' => 'admin/client-management/file/',
                'past' => null, 'pay' => null, 'approve' => null,
            ],
            2 => [
                'layout' => 'layouts/branch_admin', 'list' => 'branch-admin/client-management', 'view' => 'branch-admin/client-management/view/',
                'edit' => 'branch-admin/client-management/edit/', 'resend' => 'branch-admin/client-management/resend-login/',
                'reinstate' => 'branch-admin/client-management/reinstate/', 'note' => 'branch-admin/client-management/note/',
                'statement' => 'branch-admin/client-management/statement/', 'file' => 'branch-admin/client-management/file/',
                'past' => 'branch-admin/existing-records/client/', 'pay' => 'branch-admin/payment-tracking', 'approve' => 'branch-admin/client-management/approve/',
            ],
            default => [
                'layout' => 'layouts/staff', 'list' => 'staff/client', 'view' => 'staff/client/view/',
                'edit' => 'staff/client/edit/', 'resend' => 'staff/client/resend-login/',
                'reinstate' => 'staff/client/reinstate/', 'note' => 'staff/client/note/',
                'statement' => 'staff/client/statement/', 'file' => 'staff/client/file/',
                'past' => 'staff/existing-records/client/', 'pay' => 'staff/payment-management', 'approve' => null,
            ],
        };
    }

    /**
     * The buttons in the page header for this role.
     *
     * @return list<array<string,mixed>>
     */
    private function actions(array $client, array $cfg, array $data): array
    {
        $id = (int) $client['plan_holder_id'];
        $actions = [];

        if ($cfg['pay'] !== null) {
            $actions[] = ['label' => 'Record payment', 'icon' => 'ti-cash', 'href' => site_url($cfg['pay']) . '?search=' . rawurlencode((string) $client['first_name'] . ' ' . (string) $client['last_name']), 'class' => 'btn-primary'];
        }
        if ($cfg['past'] !== null) {
            $actions[] = ['label' => 'Add past records', 'icon' => 'ti-history', 'href' => site_url($cfg['past'] . (int) $client['user_id']), 'class' => 'btn-ghost'];
        }
        $actions[] = ['label' => 'Edit details', 'icon' => 'ti-pencil', 'href' => site_url($cfg['edit'] . $id), 'class' => 'btn-ghost'];
        $actions[] = ['label' => 'Resend login details', 'icon' => 'ti-key', 'post' => site_url($cfg['resend'] . $id), 'confirm' => "Issue a new temporary password? The client's current password will stop working.", 'class' => 'btn-ghost'];
        $actions[] = ['label' => 'Print statement', 'icon' => 'ti-printer', 'href' => site_url($cfg['statement'] . $id), 'target' => '_blank', 'class' => 'btn-ghost'];

        return $actions;
    }

    /**
     * Branch Admin only: the first payment and the Approve Registration
     * button (the page's pre-existing approval panel, kept as it was).
     *
     * @return array<string,mixed>
     */
    private function approval(array $data): array
    {
        $planId = (int) ($data['summary']['plan_id'] ?? 0);
        $db = db_connect();
        $initial = null;
        $pending = null;

        if ($planId > 0) {
            $initial = $db->table('payments')->where('plan_id', $planId)->where('status !=', '')->where('status IS NOT NULL', null, false)->orderBy('payment_date', 'ASC')->orderBy('payment_id', 'ASC')->get()->getRowArray();
            $pending = $db->table('payments')->where('plan_id', $planId)->where('status', 'pending')->orderBy('payment_date', 'DESC')->get()->getRowArray();
        }

        return [
            'initial'  => $initial,
            'can'      => (bool) $pending,
            'message'  => $pending ? '' : ($planId ? 'No pending payment found for approval.' : 'This client has no plan yet.'),
            'url'      => site_url('branch-admin/client-management/approve/' . (int) $data['client']['plan_holder_id']),
        ];
    }
}
