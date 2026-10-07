<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 * 
 * API Routes (Future Use)
 * For REST API endpoints and AJAX requests
 */

// API group for future REST endpoints
$routes->group('api', static function (RouteCollection $routes) {
    // Placeholder for future API endpoints
    // e.g., $routes->get('members', 'Api\MembersController::index');

    // Reusable Government ID Verification component (App\Services\
    // GovernmentIdVerificationService). Behind 'auth' only, same reasoning
    // as address/ above - every registration flow that needs an ID
    // verification step calls this same endpoint; the controller itself
    // always verifies against session('user_id'), never a value the
    // browser sends, so a user can only ever verify their own submission.
    $routes->group('id-verification', ['filter' => 'auth'], static function (RouteCollection $routes) {
        $routes->get('types', 'Api\IdVerificationController::idTypes');
        $routes->post('verify', 'Api\IdVerificationController::verify');
        // For Users::create() (Admin/Branch Admin/Staff creating someone
        // else's account) - role-checked inside the controller itself.
        $routes->post('verify-pending', 'Api\IdVerificationController::verifyPending');
    });
});

// AJAX endpoints
$routes->group('ajax', ['filter' => 'auth'], static function (RouteCollection $routes) {
    // Placeholder for AJAX endpoints that don't fit traditional patterns
    // e.g., $routes->post('check-eligibility', 'Ajax\EligibilityController::check');
});
