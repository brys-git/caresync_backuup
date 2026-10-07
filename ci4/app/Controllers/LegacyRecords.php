<?php

namespace App\Controllers;

use App\Services\LegacyRecordService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * "Existing member records": Branch Admin and Staff encode (or bulk import)
 * the history of members who joined before CareSync. One controller serves
 * both roles through two route groups; every action works out the role from
 * the session, scopes the client to the actor's branch, and leaves the real
 * rules (validation, pending-verification, audit logging) to
 * LegacyRecordService.
 */
class LegacyRecords extends BaseController
{
    private LegacyRecordService $records;

    public function __construct()
    {
        $this->records = new LegacyRecordService();
    }

    // ---- context helpers ----------------------------------------------

    private function actor(): array
    {
        return [
            'user_id'   => (int) session('user_id'),
            'role_id'   => (int) session('role_id'),
            'branch_id' => session('branch_id') !== null ? (int) session('branch_id') : null,
        ];
    }

    private function prefix(): string
    {
        return (int) session('role_id') === 2 ? 'branch-admin' : 'staff';
    }

    private function layout(): string
    {
        return (int) session('role_id') === 2 ? 'layouts/branch_admin' : 'layouts/staff';
    }

    private function url(string $path = ''): string
    {
        return site_url($this->prefix() . '/existing-records' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }

    private function isBranchAdmin(): bool
    {
        return (int) session('role_id') === 2;
    }

    /** The client in the actor's branch, or a 404 (same answer for "missing" and "other branch"). */
    private function client(int $userId): array
    {
        $client = $this->records->findClient($userId, $this->actor());
        if (! $client) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $client;
    }

    private function back(int $userId, string $tab, ?string $success = null, ?string $error = null, array $warnings = [])
    {
        $redirect = redirect()->to($this->url('client/' . $userId) . '?tab=' . urlencode($tab));
        if ($success) {
            $redirect->with('success', $success);
        }
        if ($error) {
            $redirect->with('error', $error);
        }
        if ($warnings) {
            $redirect->with('warning', implode(' ', $warnings));
        }

        return $redirect;
    }

    // ---- pages ---------------------------------------------------------

    public function index()
    {
        $search = cs_search_term($this->request->getGet('search'));
        $clients = $search !== '' ? $this->records->searchClients($search, $this->actor()) : [];

        return view('legacy_records/index', [
            'role_layout' => $this->layout(),
            'page_title'  => 'Existing member records',
            'page_sub'    => 'Encode the history of members who joined before CareSync.',
            'crumbs'      => [['label' => 'Members'], ['label' => 'Existing member records']],
            'search'      => $search,
            'clients'     => $clients,
            'base'        => $this->url(),
            'can_verify'  => $this->isBranchAdmin(),
            'pending'     => $this->isBranchAdmin() ? $this->records->pendingCountForBranch((int) session('branch_id')) : 0,
        ]);
    }

    public function workspace(int $userId)
    {
        $client = $this->client($userId);
        $planHolderId = (int) ($client['plan_holder_id'] ?? 0);
        $record = $planHolderId > 0 ? $this->records->getPlanRecord($planHolderId) : null;

        $tab = (string) $this->request->getGet('tab');
        if (! in_array($tab, ['plan', 'beneficiaries', 'payments', 'remittances', 'services', 'documents', 'notes'], true)) {
            $tab = 'plan';
        }

        $payments = $planHolderId > 0 ? $this->records->payments($planHolderId) : [];
        $counted = array_filter($payments, static fn ($p) => $p['record_status'] === 'verified');

        $db = db_connect();

        return view('legacy_records/workspace', [
            'role_layout'   => $this->layout(),
            'page_title'    => 'Existing member records',
            'page_sub'      => 'Past records for ' . trim($client['first_name'] . ' ' . $client['last_name']),
            'crumbs'        => [['label' => 'Members'], ['label' => 'Existing member records', 'url' => $this->url()], ['label' => trim($client['first_name'] . ' ' . $client['last_name'])]],
            'client'        => $client,
            'ledger'        => $planHolderId > 0 ? (new \App\Services\PlanLedgerService())->summarizeForPlanHolder($planHolderId) : null,
            'reinstate_url' => $planHolderId > 0 ? site_url(($this->isBranchAdmin() ? 'branch-admin/client-management' : 'staff/client') . '/reinstate/' . $planHolderId) : null,
            'record'        => $record,
            'tab'           => $tab,
            'base'          => $this->url(),
            'is_admin_role' => $this->isBranchAdmin(),
            'packages'      => $db->table('packages')->select('package_id, package_name')->orderBy('package_name', 'ASC')->get()->getResultArray(),
            'branches'      => $db->table('branches')->select('branch_id, branch_name')->orderBy('branch_name', 'ASC')->get()->getResultArray(),
            'collectors'    => $db->table('users')->select('user_id, first_name, last_name')->whereIn('role_id', [3, 5])->where('branch_id', (int) ($client['client_branch_id'] ?? 0))->where('status', 'active')->orderBy('first_name', 'ASC')->get()->getResultArray(),
            'beneficiaries' => $planHolderId > 0 ? $this->records->beneficiaries($planHolderId) : [],
            'payments'      => $payments,
            'total_paid'    => array_sum(array_map(static fn ($p) => (float) $p['amount'], $counted)),
            'months_paid'   => array_sum(array_map(static fn ($p) => (int) $p['months_covered'], $counted)),
            'missing'       => $this->records->missingMonths($planHolderId, $record['original_membership_date'] ?? null),
            'remittances'   => $planHolderId > 0 ? $this->records->rows('remittance', $planHolderId) : [],
            'services'      => $planHolderId > 0 ? $this->records->rows('service', $planHolderId) : [],
            'documents'     => $planHolderId > 0 ? $this->records->rows('document', $planHolderId) : [],
            'notes'         => $planHolderId > 0 ? $this->records->rows('note', $planHolderId) : [],
            'plan'          => $client['plan'],
            'relationships' => config(\Config\Beneficiary::class)->relationships,
            'pay_old'       => session()->getFlashdata('pay_old') ?: [],
            'pay_errors'    => session()->getFlashdata('pay_errors') ?: [],
        ]);
    }

    // ---- saving --------------------------------------------------------

    public function savePlan(int $userId)
    {
        $client = $this->client($userId);
        $res = $this->records->savePlanRecord($client, $this->request->getPost(), $this->actor(), $userId);

        if (! $res['ok']) {
            return $this->back($userId, 'plan', null, implode(' ', $res['errors']))->withInput();
        }

        return $this->back($userId, 'plan', $this->statusMessage('Plan details saved'), null, $res['warnings']);
    }

    public function addBeneficiary(int $userId)
    {
        $client = $this->client($userId);
        $res = $this->records->addBeneficiary($client, $this->request->getPost(), $this->actor());

        return $res['ok']
            ? $this->back($userId, 'beneficiaries', $this->statusMessage('Beneficiary added'))
            : $this->back($userId, 'beneficiaries', null, implode(' ', $res['errors']))->withInput();
    }

    public function addPayments(int $userId)
    {
        $client = $this->client($userId);
        $rows = $this->request->getPost('rows');
        $rows = is_array($rows) ? array_values($rows) : [];

        if ($rows === []) {
            return $this->back($userId, 'payments', null, 'Add at least one payment row.');
        }

        try {
            $res = $this->records->addPayments($client, $rows, $this->actor());
        } catch (\Throwable $e) {
            return $this->back($userId, 'payments', null, $e->getMessage());
        }

        $redirect = $this->back($userId, 'payments', $res['saved'] > 0 ? $this->statusMessage($res['saved'] . ' payment(s) saved') : null, null, array_merge($res['warnings'], $res['plan_warnings']));

        if ($res['errors'] !== []) {
            // Keep ONLY the rows that failed so they can be fixed and resubmitted.
            $keepRows = [];
            $keepErrors = [];
            foreach ($res['errors'] as $i => $messages) {
                $keepRows[] = $rows[$i];
                $keepErrors[] = $messages;
            }
            $redirect->with('pay_old', $keepRows)->with('pay_errors', $keepErrors)
                ->with('error', count($res['errors']) . ' row(s) were not saved - see the reasons below and fix them.');
        }

        return $redirect;
    }

    public function updatePayment(int $userId, int $paymentId)
    {
        $client = $this->client($userId);
        $res = $this->records->updatePayment($paymentId, $client, $this->request->getPost(), $this->actor());

        return $res['ok']
            ? $this->back($userId, 'payments', $this->statusMessage('Payment updated'), null, $res['warnings'])
            : $this->back($userId, 'payments', null, implode(' ', $res['errors']));
    }

    public function addRemittance(int $userId)
    {
        $res = $this->records->addRemittance($this->client($userId), $this->request->getPost(), $this->actor());

        return $res['ok']
            ? $this->back($userId, 'remittances', $this->statusMessage('Remittance saved'))
            : $this->back($userId, 'remittances', null, implode(' ', $res['errors']))->withInput();
    }

    public function addService(int $userId)
    {
        $res = $this->records->addService($this->client($userId), $this->request->getPost(), $this->actor());

        return $res['ok']
            ? $this->back($userId, 'services', $this->statusMessage('Service record saved'))
            : $this->back($userId, 'services', null, implode(' ', $res['errors']))->withInput();
    }

    public function addNote(int $userId)
    {
        $res = $this->records->addNote($this->client($userId), (string) $this->request->getPost('note'), $this->actor());

        return $res['ok']
            ? $this->back($userId, 'notes', $this->statusMessage('Note saved'))
            : $this->back($userId, 'notes', null, implode(' ', $res['errors']))->withInput();
    }

    public function addDocument(int $userId)
    {
        $res = $this->records->addDocument($this->client($userId), $this->request->getFile('document'), (string) $this->request->getPost('doc_type'), $this->actor());

        return $res['ok']
            ? $this->back($userId, 'documents', $this->statusMessage('Document uploaded'))
            : $this->back($userId, 'documents', null, implode(' ', $res['errors']));
    }

    /** Documents are served through here (WRITEPATH is not web-accessible), branch-scoped. */
    public function document(int $documentId): ResponseInterface
    {
        $doc = $this->records->findRecord('document', $documentId, $this->actor());
        if (! $doc || ! is_file($this->records->documentPath($doc))) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->setHeader('Content-Type', $doc['mime_type'])
            ->setHeader('Content-Disposition', 'inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $doc['original_name']) . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($this->records->documentPath($doc)));
    }

    public function delete(string $type, int $id)
    {
        $row = $this->records->findRecord($type, $id, $this->actor());
        $userId = 0;
        if ($row) {
            $userId = (int) (db_connect()->table('plan_holders')->select('user_id')->where('plan_holder_id', (int) $row['plan_holder_id'])->get()->getRowArray()['user_id'] ?? 0);
        }

        $res = $this->records->delete($type, $id, $this->actor());
        $tab = ['payment' => 'payments', 'remittance' => 'remittances', 'service' => 'services', 'document' => 'documents', 'note' => 'notes', 'beneficiary' => 'beneficiaries'][$type] ?? 'plan';

        if ($userId === 0) {
            return redirect()->to($this->url())->with('error', $res['message']);
        }

        return $res['ok'] ? $this->back($userId, $tab, $res['message']) : $this->back($userId, $tab, null, $res['message']);
    }

    // ---- verification (Branch Admin) ----------------------------------

    public function pending()
    {
        return view('legacy_records/pending', [
            'role_layout' => $this->layout(),
            'page_title'  => 'Past records to verify',
            'page_sub'    => 'Records encoded by Staff count toward a client\'s totals only after you verify them.',
            'crumbs'      => [['label' => 'Members'], ['label' => 'Existing member records', 'url' => $this->url()], ['label' => 'To verify']],
            'groups'      => $this->records->pendingForBranch((int) session('branch_id')),
            'base'        => $this->url(),
            'labels'      => ['channel' => LegacyRecordService::CHANNELS],
        ]);
    }

    public function verify(string $type, int $id)
    {
        $res = $this->records->verify($type, $id, $this->actor());

        return redirect()->to($this->url('pending'))->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function reject(string $type, int $id)
    {
        $res = $this->records->reject($type, $id, (string) $this->request->getPost('reason'), $this->actor());

        return redirect()->to($this->url('pending'))->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    /** Verify everything pending for one client in one click. */
    public function verifyClient(int $planHolderId)
    {
        $count = 0;
        $messages = [];
        foreach ($this->records->pendingForBranch((int) session('branch_id')) as $group) {
            if ((int) $group['plan_holder_id'] !== $planHolderId) {
                continue;
            }
            // Plan details first, then payments (so the plan exists when totals are recomputed).
            usort($group['items'], static fn ($a, $b) => ($a['_type'] === 'plan' ? 0 : 1) <=> ($b['_type'] === 'plan' ? 0 : 1));
            foreach ($group['items'] as $item) {
                $res = $this->records->verify($item['_type'], (int) $item['_id'], $this->actor());
                if ($res['ok']) {
                    $count++;
                    if ($res['message'] !== 'Verified.') {
                        $messages[] = $res['message'];
                    }
                }
            }
        }

        $redirect = redirect()->to($this->url('pending'))->with('success', $count . ' record(s) verified.');

        return $messages ? $redirect->with('warning', implode(' ', array_unique($messages))) : $redirect;
    }

    // ---- bulk import ---------------------------------------------------

    public function importForm()
    {
        return view('legacy_records/import', [
            'role_layout' => $this->layout(),
            'page_title'  => 'Import past payments',
            'page_sub'    => 'Upload a CSV or Excel file, check the preview, then confirm.',
            'crumbs'      => [['label' => 'Members'], ['label' => 'Existing member records', 'url' => $this->url()], ['label' => 'Import']],
            'base'        => $this->url(),
            'headers'     => LegacyRecordService::IMPORT_HEADERS,
            'preview'     => null,
        ]);
    }

    public function importTemplate(): ResponseInterface
    {
        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="past-payments-template.csv"')
            ->setBody($this->records->templateCsv());
    }

    public function importPreview()
    {
        $file = $this->request->getFile('import_file');
        if (! $file || ! $file->isValid()) {
            return redirect()->to($this->url('import'))->with('error', 'Choose a .csv or .xlsx file to upload.');
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return redirect()->to($this->url('import'))->with('error', 'That file is larger than 10 MB.');
        }

        $ext = strtolower($file->getClientExtension());
        if (! in_array($ext, ['csv', 'xlsx'], true)) {
            return redirect()->to($this->url('import'))->with('error', 'Only .csv and .xlsx files can be imported.');
        }

        try {
            $preview = $this->records->previewImport($file->getTempName(), $ext, $this->actor());
        } catch (\Throwable $e) {
            return redirect()->to($this->url('import'))->with('error', $e->getMessage());
        }

        return view('legacy_records/import', [
            'role_layout' => $this->layout(),
            'page_title'  => 'Import past payments',
            'page_sub'    => 'Check the preview, then confirm.',
            'crumbs'      => [['label' => 'Members'], ['label' => 'Existing member records', 'url' => $this->url()], ['label' => 'Import']],
            'base'        => $this->url(),
            'headers'     => LegacyRecordService::IMPORT_HEADERS,
            'preview'     => $preview,
        ]);
    }

    public function importCommit()
    {
        try {
            $res = $this->records->commitImport((string) $this->request->getPost('token'), $this->actor());
        } catch (\Throwable $e) {
            return redirect()->to($this->url('import'))->with('error', $e->getMessage());
        }

        $message = $res['saved'] . ' payment(s) imported' . ($res['skipped'] > 0 ? ', ' . $res['skipped'] . ' skipped.' : '.');
        if (! $this->isBranchAdmin()) {
            $message .= ' They are waiting for your Branch Admin to verify them.';
        }

        return redirect()->to($this->url('import'))->with('success', $message);
    }

    public function importErrors(string $token): ResponseInterface
    {
        $data = $this->records->loadImport($token, $this->actor());
        if (! $data) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="past-payments-errors.csv"')
            ->setBody($this->records->errorRowsCsv($data));
    }

    private function statusMessage(string $what): string
    {
        return $this->isBranchAdmin()
            ? $what . ' and verified.'
            : $what . '. It is waiting for your Branch Admin to verify before it counts toward the client\'s totals.';
    }
}
