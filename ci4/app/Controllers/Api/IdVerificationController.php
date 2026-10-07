<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\GovernmentIdVerificationService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Thin, role-agnostic API surface over GovernmentIdVerificationService -
 * mirrors Api\AddressController's pattern (a plain service class doing
 * all the real work, a small controller just shaping the HTTP request/
 * response around it). Every registration flow's Government ID step
 * calls this same endpoint; only session('user_id') is ever trusted for
 * whose identity is being verified - never a value submitted from the
 * browser - so a user can only ever verify their own submission.
 */
class IdVerificationController extends BaseController
{
    private const FRIENDLY_ERROR = 'Unable to verify your ID right now. Please try again.';

    public function verify(): ResponseInterface
    {
        $userId = (int) session('user_id');
        if ($userId <= 0) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Please sign in first.']);
        }

        $idType = trim((string) $this->request->getPost('id_type'));
        $firstName = trim((string) $this->request->getPost('first_name'));
        $middleName = trim((string) $this->request->getPost('middle_name'));
        $lastName = trim((string) $this->request->getPost('last_name'));
        $dateOfBirth = trim((string) $this->request->getPost('date_of_birth'));
        $gender = trim((string) $this->request->getPost('gender'));

        $file = $this->request->getFile('id_image');
        if (! $file) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Please choose or capture an ID image.']);
        }

        try {
            $service = new GovernmentIdVerificationService();
            $result = $service->verify($userId, $idType, $file, [
                'first_name'    => $firstName,
                'middle_name'   => $middleName,
                'last_name'     => $lastName,
                'date_of_birth' => $dateOfBirth,
                'gender'        => $gender,
            ]);

            return $this->response->setJSON([
                'status'          => $result['status'],
                'message'         => $result['message'],
                'verification_id' => $result['verification_id'],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'IdVerificationController::verify - ' . $e->getMessage());

            return $this->response->setStatusCode(502)->setJSON(['error' => self::FRIENDLY_ERROR]);
        }
    }

    /**
     * Same as verify(), but for Users::create() (Admin/Branch Admin/
     * Staff creating someone else's account) - the new account doesn't
     * exist yet, so the result is stashed under a random token instead
     * of a user_id. Users::store() calls GovernmentIdVerificationService::
     * commitPending() with this token once the account is actually
     * created. Restricted to the same roles allowed to reach users/
     * create in the first place.
     */
    public function verifyPending(): ResponseInterface
    {
        $roleId = (int) session('role_id');
        if (! in_array($roleId, [1, 2, 3], true)) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Not authorized.']);
        }

        $idType = trim((string) $this->request->getPost('id_type'));
        $firstName = trim((string) $this->request->getPost('first_name'));
        $middleName = trim((string) $this->request->getPost('middle_name'));
        $lastName = trim((string) $this->request->getPost('last_name'));
        $dateOfBirth = trim((string) $this->request->getPost('date_of_birth'));
        $gender = trim((string) $this->request->getPost('gender'));

        $file = $this->request->getFile('id_image');
        if (! $file) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Please choose or capture an ID image.']);
        }

        try {
            $service = new GovernmentIdVerificationService();
            $result = $service->verifyPending($idType, $file, [
                'first_name'    => $firstName,
                'middle_name'   => $middleName,
                'last_name'     => $lastName,
                'date_of_birth' => $dateOfBirth,
                'gender'        => $gender,
            ]);

            return $this->response->setJSON([
                'status'        => $result['status'],
                'message'       => $result['message'],
                'pending_token' => $result['pending_token'],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'IdVerificationController::verifyPending - ' . $e->getMessage());

            return $this->response->setStatusCode(502)->setJSON(['error' => self::FRIENDLY_ERROR]);
        }
    }

    /**
     * The available ID types for the dropdown - configurable in
     * Config\GovernmentIdVerification, not hard-coded per form.
     */
    public function idTypes(): ResponseInterface
    {
        if (! session()->has('user_id')) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Please sign in first.']);
        }

        $service = new GovernmentIdVerificationService();

        return $this->response->setJSON(['data' => $service->idTypes()]);
    }
}
