<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class ProfileController extends BaseController
{
    public function index(): string
    {
        return $this->renderProfilePage(false);
    }

    public function edit(): string
    {
        return $this->renderProfilePage(true);
    }

    public function update()
    {
        $user = $this->getCurrentAdmin();

        $rules = [
            'email' => 'required|valid_email|max_length[100]',
            'contact_number' => 'required|max_length[30]',
            'first_name' => 'required|max_length[50]',
            'middle_name' => 'permit_empty|max_length[50]',
            'last_name' => 'required|max_length[50]',
            'name_extension' => 'permit_empty|max_length[20]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $userId = (int) $user['user_id'];
        $email = trim((string) $this->request->getPost('email'));

        $userModel = new UserModel();
        $existingByEmail = $userModel
            ->where('email', $email)
            ->where('user_id !=', $userId)
            ->first();

        if ($existingByEmail) {
            return redirect()->back()->withInput()->with('error', 'Email is already in use by another account.');
        }

        $updated = $userModel->update($userId, [
            'email' => $email,
            'contact_number' => trim((string) $this->request->getPost('contact_number')),
            'first_name' => trim((string) $this->request->getPost('first_name')),
            'middle_name' => trim((string) $this->request->getPost('middle_name')),
            'last_name' => trim((string) $this->request->getPost('last_name')),
            'name_extension' => trim((string) $this->request->getPost('name_extension')),
        ]);

        if (! $updated) {
            return redirect()->back()->withInput()->with('error', 'Invalid input or password incorrect');
        }

        return redirect()->to('/admin/profile')->with('success', 'Profile updated successfully');
    }

    public function changePassword()
    {
        $user = $this->getCurrentAdmin();

        $rules = [
            'current_password' => 'required',
            'new_password' => 'required|min_length[8]|max_length[255]',
            'confirm_password' => 'required|matches[new_password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $currentPassword = (string) $this->request->getPost('current_password');
        $newPassword = (string) $this->request->getPost('new_password');

        if (! password_verify($currentPassword, (string) $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Invalid input or password incorrect');
        }

        $updated = (new UserModel())->update((int) $user['user_id'], [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ]);

        if (! $updated) {
            return redirect()->back()->withInput()->with('error', 'Invalid input or password incorrect');
        }

        session()->set('must_change_password', 0);

        return redirect()->to('/admin/profile')->with('success', 'Password updated successfully');
    }

    private function renderProfilePage(bool $editMode): string
    {
        $user = $this->getCurrentAdmin();

        return view('admin/profile/index', [
            'role_layout' => 'layouts/admin',
            'page_title' => 'System Admin Profile',
            'user' => $user,
            'edit_mode' => $editMode,
        ]);
    }

    private function getCurrentAdmin(): array
    {
        $userId = (int) session('user_id');
        $roleId = (int) session('role_id');

        if ($userId <= 0 || $roleId !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        $user = (new UserModel())
            ->select('user_id, username, email, first_name, middle_name, last_name, name_extension, contact_number, password_hash, role_id, account_status, status, last_login')
            ->find($userId);

        if (! $user || (int) ($user['role_id'] ?? 0) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $user;
    }
}
