<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $roleId = (int) session('role_id');

        if ($roleId === 0) {
            return redirect()->to('/login')->with('error', 'Please sign in first.');
        }

        $allowedRoles = array_map('intval', $arguments ?? []);

        if ($allowedRoles !== [] && ! in_array($roleId, $allowedRoles, true)) {
            return redirect()->to($this->defaultDashboardForRole($roleId))
                ->with('error', 'You are not allowed to access that page.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function defaultDashboardForRole(int $roleId): string
    {
        return match ($roleId) {
            1 => '/dashboard/admin',
            2 => '/dashboard/branch-admin',
            3 => '/dashboard/staff',
            4 => '/dashboard/plan-holder',
            5 => '/dashboard/collector',
            default => '/login',
        };
    }
}
