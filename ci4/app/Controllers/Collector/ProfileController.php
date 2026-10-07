<?php

namespace App\Controllers\Collector;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Mirrors Staff\ProfileController for role_id 5 (dedicated Collector
 * accounts - distinct from a Staff account with the is_collector flag,
 * which already uses staff/profile). Added because topbar.php and
 * sidebar.php generate "{$cs_role}/profile" for every role ('collector'
 * for this one) but no route for it ever existed, so "My profile" and
 * "Account settings" 404'd for every Collector account, on every page.
 */
class ProfileController extends BaseController
{
    public function index(): string
    {
        $user = $this->getCurrentCollector();

        return view('staff/profile/index', [
            'role_layout' => 'layouts/collector',
            'page_title' => 'My Profile',
            'user' => $user,
            'update_url' => '/collector/profile/update',
        ]);
    }

    public function update()
    {
        $user = $this->getCurrentCollector();

        $rules = [
            'email' => 'required|valid_email|max_length[100]',
            'contact_number' => 'permit_empty|max_length[30]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $email = trim((string) $this->request->getPost('email'));
        $contactNumber = trim((string) $this->request->getPost('contact_number'));

        $userModel = new UserModel();
        $existingByEmail = $userModel
            ->where('email', $email)
            ->where('user_id !=', (int) $user['user_id'])
            ->first();

        if ($existingByEmail) {
            return redirect()->back()->withInput()->with('error', 'Email is already in use by another account.');
        }

        $updated = $userModel->update((int) $user['user_id'], [
            'email' => $email,
            'contact_number' => $contactNumber === '' ? null : $contactNumber,
        ]);

        if (! $updated) {
            return redirect()->back()->withInput()->with('error', 'Failed to update profile.');
        }

        return redirect()->to('/collector/profile')->with('success', 'Profile updated successfully.');
    }

    private function getCurrentCollector(): array
    {
        $userId = (int) session('user_id');
        $roleId = (int) session('role_id');

        if ($userId <= 0 || $roleId !== 5) {
            throw PageNotFoundException::forPageNotFound();
        }

        $user = (new UserModel())
            ->select('user_id, username, first_name, middle_name, last_name, name_extension, email, contact_number, branch_id, role_id')
            ->find($userId);

        if (! $user || (int) ($user['role_id'] ?? 0) !== 5) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $user;
    }
}
