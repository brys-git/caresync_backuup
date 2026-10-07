<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\UserModel;
use App\Models\PlanHolderModel;
use App\Config\ValidationRules;
use App\Helpers\QueryHelper;

/**
 * ClientProfileController
 *
 * Handles client profile management
 * Part of the refactored ClientPortal controller
 *
 * Uses centralized validation rules and query helpers
 * to reduce code duplication
 */
class ClientProfileController extends BaseController
{
    use ClientPortalTrait;

    private const PHOTO_MAX_BYTES = 5 * 1024 * 1024;

    private const PHOTO_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Display client profile
     */
    public function profile(): ResponseInterface|string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }
        $planHolder = $access['plan_holder'];
        $userRow = (new UserModel())->find((int) $access['user']['user_id']) ?? $access['user'];

        // "Edit Profile" used to link to a /client/profile/edit route that
        // never existed, and edit_mode was never set at all - so the edit
        // form on this same page could never actually be reached. Toggle it
        // via a query flag instead, matching the pattern already used for
        // ?tab= elsewhere in the client portal. Found during the
        // 2026-09-09 system scan.
        $editMode = $this->request->getGet('edit') === '1';

        $plan = $planHolder ? $this->latestPlan((int) $planHolder['plan_holder_id']) : null;

        return view('client/profile', [
            'role_layout' => 'layouts/plan_holder',
            'page_title' => 'My profile',
            'show_page_head' => false,
            'user' => $userRow,
            'plan_holder' => $planHolder,
            'plan' => $plan,
            'photo_url' => ! empty($userRow['profile_photo'])
                ? base_url('client/profile/photo') . '?v=' . urlencode((string) $userRow['profile_photo'])
                : null,
            'access' => $access,
            'edit_mode' => $editMode,
            'form_errors' => session()->getFlashdata('form_errors') ?? [],
            'password_open' => (bool) session()->getFlashdata('password_open'),
            'password_updated' => (bool) session()->getFlashdata('password_updated'),
        ]);
    }

    /**
     * Update client profile information
     */
    public function updateProfile()
    {
        try {
            $user = $this->currentUser();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        $userId = (int) $user['user_id'];

        // Use centralized validation rules with email uniqueness check
        $rules = array_merge(ValidationRules::getProfileRulesWithEmailUniqueness($userId), [
            'place_of_birth' => 'permit_empty|max_length[100]',
            'height' => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[300]',
            'weight' => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[500]',
            'senior_citizen_id' => 'permit_empty|max_length[50]',
            'organization_affiliation' => 'permit_empty|max_length[100]',
            'spouse_name' => 'permit_empty|max_length[100]',
            'spouse_birthdate' => 'permit_empty|valid_date[Y-m-d]',
            'spouse_occupation' => 'permit_empty|max_length[100]',
            'emergency_contact_name' => 'permit_empty|max_length[100]',
            'emergency_contact_number' => 'permit_empty|max_length[30]',
            'emergency_contact_address' => 'permit_empty|max_length[150]',
        ]);
        $messages = ValidationRules::getValidationMessages();

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        // Check email uniqueness using QueryHelper
        if (QueryHelper::emailExists(trim((string) $this->request->getPost('email')), $userId)) {
            return redirect()->back()->withInput()->with('error', 'Email is already in use by another account.');
        }

        $userModel = new UserModel();
        $planHolderModel = new PlanHolderModel();

        $db = db_connect();
        $db->transBegin();

        try {
            $userModel->update($userId, [
                'email' => trim((string) $this->request->getPost('email')),
                'contact_number' => trim((string) $this->request->getPost('contact_number')),
                'first_name' => trim((string) $this->request->getPost('first_name')),
                'last_name' => trim((string) $this->request->getPost('last_name')),
            ]);

            $planHolder = $this->currentPlanHolder();
            if ($planHolder) {
                $planHolderModel->update((int) $planHolder['plan_holder_id'], [
                    'address_barangay' => trim((string) $this->request->getPost('address_barangay')),
                    'address_city' => trim((string) $this->request->getPost('address_city')),
                    'civil_status' => trim((string) $this->request->getPost('civil_status')),
                    'citizenship' => trim((string) $this->request->getPost('citizenship')),
                    'place_of_birth' => $this->optionalPost('place_of_birth'),
                    'height' => $this->optionalPost('height'),
                    'weight' => $this->optionalPost('weight'),
                    'senior_citizen_id' => $this->optionalPost('senior_citizen_id'),
                    'organization_affiliation' => $this->optionalPost('organization_affiliation'),
                    'spouse_name' => $this->optionalPost('spouse_name'),
                    'spouse_birthdate' => $this->optionalPost('spouse_birthdate'),
                    'spouse_occupation' => $this->optionalPost('spouse_occupation'),
                    'emergency_contact_name' => $this->optionalPost('emergency_contact_name'),
                    'emergency_contact_number' => $this->optionalPost('emergency_contact_number'),
                    'emergency_contact_address' => $this->optionalPost('emergency_contact_address'),
                ]);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Unable to save profile details.');
            }

            $db->transCommit();

            return redirect()->to('/client/profile')->with('success', 'Profile updated successfully.');
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * The old standalone change-password page is gone - its form now lives
     * inline on My profile, so any old link lands there.
     */
    public function changePassword(): ResponseInterface
    {
        return redirect()->to('/client/profile#security');
    }

    /**
     * Update user password
     */
    public function updatePassword()
    {
        try {
            $user = $this->currentUser();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        $userId = (int) $user['user_id'];

        // Validate password fields
        $rules = [
            'current_password' => 'required|min_length[6]',
            'new_password' => 'required|min_length[8]|differs[current_password]',
            'confirm_password' => 'required|matches[new_password]',
        ];

        $messages = [
            'current_password' => [
                'required' => 'Current password is required',
                'min_length' => 'Current password must be at least 6 characters',
            ],
            'new_password' => [
                'required' => 'New password is required',
                'min_length' => 'New password must be at least 8 characters',
                'differs' => 'New password must be different from current password',
            ],
            'confirm_password' => [
                'required' => 'Password confirmation is required',
                'matches' => 'Passwords do not match',
            ],
        ];

        if (! $this->validate($rules, $messages)) {
            return $this->passwordError($this->validator->getErrors());
        }

        $userModel = new UserModel();
        $currentPassword = trim((string) $this->request->getPost('current_password'));
        $newPassword = trim((string) $this->request->getPost('new_password'));

        // Verify current password
        if (! password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
            return $this->passwordError(['current_password' => 'Current password is incorrect.']);
        }

        // Check if new password contains username
        if (stripos($newPassword, (string) ($user['username'] ?? '')) !== false) {
            return $this->passwordError(['new_password' => 'New password cannot contain your username.']);
        }

        try {
            $userModel->update($userId, [
                'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
                'password_changed_at' => date('Y-m-d H:i:s'),
            ]);

            return redirect()->to('/client/profile#security')->with('password_updated', true);
        } catch (\Throwable $e) {
            return $this->passwordError(['new_password' => 'Failed to update password. Please try again.']);
        }
    }

    /**
     * Profile photo: image only, 5 MB max. Cropped to a square when the GD
     * extension is available; otherwise kept as uploaded and cropped in the
     * avatar with CSS.
     */
    public function uploadPhoto()
    {
        try {
            $user = $this->currentUser();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        $file = $this->request->getFile('photo');
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return redirect()->to('/client/profile')->with('error', 'Choose an image to upload.');
        }
        if (! $file->isValid()) {
            return redirect()->to('/client/profile')->with('error', 'The photo could not be uploaded. Please try again.');
        }
        if ($file->getSize() > self::PHOTO_MAX_BYTES) {
            return redirect()->to('/client/profile')->with('error', 'Photos must be 5 MB or smaller.');
        }
        $extension = self::PHOTO_TYPES[(string) $file->getMimeType()] ?? null;
        if ($extension === null) {
            return redirect()->to('/client/profile')->with('error', 'Upload a JPG, PNG, or WebP image.');
        }

        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'profile_photos';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $userId = (int) $user['user_id'];
        $name = $userId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $file->move($dir, $name);
        $path = $dir . DIRECTORY_SEPARATOR . $name;

        if (extension_loaded('gd')) {
            \Config\Services::image()->withFile($path)->fit(512, 512, 'center')->save($path);
        }

        $userModel = new UserModel();
        $previous = (string) ($userModel->find($userId)['profile_photo'] ?? '');
        $userModel->update($userId, ['profile_photo' => $name]);
        if ($previous !== '' && is_file($dir . DIRECTORY_SEPARATOR . basename($previous))) {
            unlink($dir . DIRECTORY_SEPARATOR . basename($previous));
        }

        return redirect()->to('/client/profile')->with('success', 'Profile photo updated.');
    }

    /**
     * Serves the signed-in member's own photo.
     */
    public function photo(): ResponseInterface
    {
        $user = $this->currentUser();
        $name = basename((string) ((new UserModel())->find((int) $user['user_id'])['profile_photo'] ?? ''));
        $full = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'profile_photos' . DIRECTORY_SEPARATOR . $name;

        if ($name === '' || ! is_file($full)) {
            return $this->response->setStatusCode(404);
        }

        return $this->response
            ->setContentType((string) (mime_content_type($full) ?: 'image/jpeg'))
            ->setHeader('Cache-Control', 'private, max-age=300')
            ->setBody((string) file_get_contents($full));
    }

    private function optionalPost(string $key): ?string
    {
        $value = trim((string) $this->request->getPost($key));

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string,string> $errors
     */
    private function passwordError(array $errors)
    {
        return redirect()->to('/client/profile#security')
            ->with('form_errors', $errors)
            ->with('password_open', true);
    }
}
