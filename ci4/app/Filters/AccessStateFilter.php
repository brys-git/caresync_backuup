<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Services\ClientRegistrationService;

/**
 * AccessStateFilter
 * 
 * Enforces access control based on client registration state
 * - new: Account only, no registration (redirect to registration)
 * - pending: Registered, awaiting payment approval (restrict to registration & payment pages)
 * - approved: Active member (full access)
 * 
 * Usage in routes:
 * $routes->get('service', 'Client\ClientServiceController::services', ['filter' => 'accessState:approved']);
 * $routes->get('initial-payment', 'Client\ClientPaymentInitialController::initialPayment', ['filter' => 'accessState:pending,approved']);
 */
class AccessStateFilter implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     * By default it should not alter the request or response,
     * unless it needs to.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return RequestInterface|ResponseInterface|string|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $userId = (int) session('user_id');
        $roleId = (int) session('role_id');

        // Only apply to plan holders (role_id = 4)
        if ($userId <= 0 || $roleId !== 4) {
            return;
        }

        // Get current access state
        $registrationService = new ClientRegistrationService();
        $accessState = $registrationService->determineAccessState($userId);
        $currentState = $accessState['state'];

        // If no allowed states specified, allow all
        if (empty($arguments)) {
            return;
        }

        $allowedStates = explode(',', $arguments[0]);
        $allowedStates = array_map('trim', $allowedStates);

        // Check if current state is allowed
        if (!in_array($currentState, $allowedStates, true)) {
            // Handle redirection based on current state
            if ($currentState === 'new') {
                return redirect()->to('/plan-info')->with('error', 'Please register to access this page.');
            } elseif ($currentState === 'pending') {
                return redirect()->to('/initial-payment')->with('error', 'Complete your initial payment to access this page.');
            } elseif ($currentState === 'approved') {
                return redirect()->to('/unauthorized')->with('error', 'You do not have permission to access this page.');
            }
        }
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not need to do anything
     * and has no required parameters, other than the `Request` and
     * `Response` objects that must be present in both the Before and After
     * filter.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return ResponseInterface|void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
