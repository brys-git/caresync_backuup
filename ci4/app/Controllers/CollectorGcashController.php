<?php

namespace App\Controllers;

use App\Services\CollectorGcashService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Where a collector (a role 5 account, or Staff flagged is_collector - same
 * can_collect() capability gate as the Collection List) sets the GCash
 * number and QR code clients in their area are shown on the Make Payment
 * page. See CollectorGcashService::resolveForClient() for how a client is
 * matched to a collector.
 */
class CollectorGcashController extends BaseController
{
    private CollectorGcashService $service;

    public function __construct()
    {
        $this->service = new CollectorGcashService();
    }

    public function index(): ResponseInterface|string
    {
        $account = $this->service->forCollector((int) session('user_id'));

        return view('collector_gcash/index', [
            'role_layout' => $this->layout(),
            'page_title'  => 'My GCash account',
            'page_sub'    => 'Clients in your assigned area see this number and QR code when they pay online.',
            'account'     => $account,
        ]);
    }

    public function save(): ResponseInterface
    {
        $userId = (int) session('user_id');
        $existing = $this->service->forCollector($userId);

        $rules = [
            'gcash_number' => 'required|max_length[20]',
            'account_name' => 'permit_empty|max_length[100]',
            'qr'           => 'permit_empty|is_image[qr]|mime_in[qr,image/jpg,image/jpeg,image/png,image/webp]|max_size[qr,2048]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $number = $this->normalizeNumber((string) $this->request->getPost('gcash_number'));
        if ($number === null) {
            return redirect()->back()->withInput()->with('error', 'Enter a valid GCash mobile number, e.g. 09171234567.');
        }

        $qrPath = null;
        $file = $this->request->getFile('qr');
        if ($file && $file->isValid() && ! $file->hasMoved()) {
            $targetDir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'gcash_qr' . DIRECTORY_SEPARATOR . $userId;
            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0775, true);
            }

            $newName = $file->getRandomName();
            $file->move($targetDir, $newName);
            $qrPath = 'uploads/gcash_qr/' . $userId . '/' . $newName;
        }

        $removeQr = $qrPath === null && $this->request->getPost('remove_qr') === '1';

        $this->service->save(
            $userId,
            $number,
            trim((string) $this->request->getPost('account_name')),
            $qrPath,
            $removeQr
        );

        if (($qrPath !== null || $removeQr) && ! empty($existing['qr_path'])) {
            $old = WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, (string) $existing['qr_path']);
            if (is_file($old)) {
                @unlink($old);
            }
        }

        return redirect()->to('/collector/gcash-account')->with('success', 'GCash account saved.');
    }

    /**
     * Streams a collector's QR image. Not under public/ on purpose (uploads
     * live in WRITEPATH like every other file here), so it goes through the
     * app: any signed-in staff/admin/collector may view it, but a plan holder
     * only the QR of the collector actually resolved for THEIR address - not
     * any collector's by guessing ids.
     */
    public function qr(int $collectorUserId): ResponseInterface
    {
        if ((int) session('role_id') === 4) {
            $holder = db_connect()->table('plan_holders')
                ->select('branch_id, barangay_code')
                ->where('user_id', (int) session('user_id'))
                ->get()
                ->getRowArray();

            $resolved = $holder
                ? $this->service->resolveForClient((int) $holder['branch_id'], $holder['barangay_code'] ?? null)
                : null;

            if (! $resolved || $resolved['user_id'] !== $collectorUserId) {
                return $this->response->setStatusCode(404);
            }
        }

        $account = $this->service->forCollector($collectorUserId);
        $full = $account && ! empty($account['qr_path'])
            ? WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, (string) $account['qr_path'])
            : '';

        if ($full === '' || ! is_file($full)) {
            return $this->response->setStatusCode(404);
        }

        return $this->response
            ->setContentType((string) (mime_content_type($full) ?: 'image/png'))
            ->setHeader('Cache-Control', 'private, max-age=300')
            ->setBody((string) file_get_contents($full));
    }

    /** 09XXXXXXXXX, from 09…, +639…, 639… with spaces/dashes tolerated. */
    private function normalizeNumber(string $raw): ?string
    {
        $digits = preg_replace('/[\s\-()]/', '', $raw) ?? '';

        if (preg_match('/^(?:\+?63|0)(9\d{9})$/', $digits, $m)) {
            return '0' . $m[1];
        }

        return null;
    }

    private function layout(): string
    {
        return (int) session('role_id') === 3 ? 'layouts/staff' : 'layouts/collector';
    }
}
