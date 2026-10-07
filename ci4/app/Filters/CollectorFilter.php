<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Gates a route to whoever can_collect() says can collect - a dedicated
 * Collector account (role_id 5), or a Staff account (role_id 3) marked
 * users.is_collector. This is the ONLY place a collection route checks
 * that, via the can_collect() helper (app/Helpers/caresync_helper.php) -
 * never role_id/is_collector comparisons inline in a controller or view.
 *
 * Distinct from RoleFilter's 'role:3,5' because that alone would let in
 * every Staff account, not just ones flagged as collectors. Apply this
 * filter alongside 'auth' on collection-entry routes (the Collection List,
 * Record Payment); role-only pages (the Collector dashboard) still use
 * 'role:5' as before.
 */
class CollectorFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if ((int) session('user_id') <= 0) {
            return redirect()->to('/login')->with('error', 'Please sign in first.');
        }

        if (! can_collect()) {
            return redirect()->to('/unauthorized')->with('error', 'You are not allowed to access that page.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
