<?php

namespace App\Controllers;

use App\Services\NotificationService;

class Notifications extends BaseController
{
    public function index(): string
    {
        $type = trim((string) $this->request->getGet('type'));
        $status = trim((string) $this->request->getGet('status'));
        $notifications = (new NotificationService())->getNotifications((int) session('user_id'), $type, $status);

        return view('notifications/index', [
            'notifications' => $notifications,
            'selected_type' => $type,
            'selected_status' => $status,
            'role_layout' => $this->resolveLayoutView(),
            'base_path' => $this->resolveBasePath(),
            'page_title' => 'Notifications',
        ]);
    }

    public function markRead()
    {
        $rules = [
            'notification_id' => 'required|is_natural_no_zero',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', 'Invalid notification selection.');
        }

        $notificationService = new NotificationService();
        $notification = db_connect()->table('notifications')
            ->where('notification_id', (int) $this->request->getPost('notification_id'))
            ->where('user_id', (int) session('user_id'))
            ->get()
            ->getRowArray();

        if (! $notification) {
            return redirect()->back()->with('error', 'Notification not found.');
        }

        $notificationService->markAsRead((int) $notification['notification_id']);

        return redirect()->back()->with('success', 'Notification marked as read.');
    }

    private function resolveLayoutView(): string
    {
        $role = (int) session()->get('role_id');

        if ($role === 1) {
            return 'layouts/admin';
        }

        if ($role === 2) {
            return 'layouts/branch_admin';
        }

        if ($role === 3) {
            return 'layouts/staff';
        }

        return 'layouts/plan_holder';
    }

    /**
     * The view's filter form and mark-read form both post back to this
     * controller, but under two different routed paths depending on role:
     * plan holders (role_id 4) go through client/notification (routed in
     * Routes/client.php, filter role:4), everyone else through the bare
     * notifications route added alongside this fix (Routes.php, filter
     * role:1,2,3). Posting to the wrong one 403s, since each route only
     * allows its own role filter.
     */
    private function resolveBasePath(): string
    {
        return (int) session()->get('role_id') === 4 ? 'client/notification' : 'notifications';
    }
}
