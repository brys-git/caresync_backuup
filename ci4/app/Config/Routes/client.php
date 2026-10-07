<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 * 
 * Plan Holder / Client Routes (Role: 4)
 * All routes require 'auth' and 'role:4' filters unless specified
 */

// Dashboard & Membership Routes
$routes->group('client', ['filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('dashboard', 'Client\ClientDashboardController::dashboard', ['filter' => 'role:4']);
    $routes->get('membership', 'Client\ClientMembershipController::membership', ['filter' => 'role:4']);

    // Profile Management
    $routes->get('profile', 'Client\ClientProfileController::profile', ['filter' => 'role:4']);
    $routes->post('profile/update', 'Client\ClientProfileController::updateProfile', ['filter' => 'role:4']);
    $routes->get('profile/change-password', 'Client\ClientProfileController::changePassword', ['filter' => 'role:4']);
    $routes->post('profile/update-password', 'Client\ClientProfileController::updatePassword', ['filter' => 'role:4']);
    $routes->post('profile/photo', 'Client\ClientProfileController::uploadPhoto', ['filter' => 'role:4']);
    $routes->get('profile/photo', 'Client\ClientProfileController::photo', ['filter' => 'role:4']);

    // Payment Management
    $routes->get('payment', 'Client\ClientPaymentController::payment', ['filter' => 'role:4']);
    // Task 1: Payment History merged back into client/payment - keep the
    // old URL working (bookmarks, the dashboard quick action) as a redirect.
    // addRedirect()'s $to is NOT prefixed by the enclosing group (unlike
    // get()/post()/etc.) - it's used verbatim, so this must be fully
    // qualified or it redirects to site-root /payment instead.
    $routes->addRedirect('payment/history', 'client/payment', 301);
    // Phase 3: Make Payment, its own page.
    $routes->get('payment/make', 'Client\ClientPaymentController::makePayment', ['filter' => 'role:4']);
    $routes->post('payment/make', 'Client\ClientPaymentController::submitPayment', ['filter' => 'role:4']);
    // Phase 1: per-transaction receipt (was a single "latest payment"
    // dashboard quick action - now a link on each verified row in Payment
    // History instead).
    $routes->get('payment/download-receipt/(:num)', 'Client\ClientPaymentController::downloadReceipt/$1', ['filter' => 'role:4']);
    $routes->post('payment/submit-gcash', 'Client\ClientPaymentController::submitGcashPayment', ['filter' => 'role:4']);

    // Service balance continuation
    $routes->get('service-balances', 'ServiceBalances::index', ['filter' => 'role:4']);
    $routes->get('service-balances/(:num)', 'ServiceBalances::show/$1', ['filter' => 'role:4']);
    $routes->post('service-balances/acknowledge/(:num)', 'ServiceBalances::acknowledge/$1', ['filter' => 'role:4']);
    $routes->post('service-balances/pay/(:num)', 'ServiceBalances::pay/$1', ['filter' => 'role:4']);

    // Service Management
    $routes->get('service', 'Client\ClientServiceController::services', ['filter' => 'role:4']);
    $routes->get('service/(:num)', 'Client\ClientServiceController::serviceDetails/$1', ['filter' => 'role:4']);
    $routes->get('package/(:num)', 'Client\ClientServiceController::packageDetails/$1', ['filter' => 'role:4']);

    // Redirect legacy URLs to canonical routes (301 Permanent)
    $routes->addRedirect('service/apply/service/(:any)', 'apply-service/$1', 301);

    // (legacy aliases removed) Use canonical apply-service/apply-package routes instead

    // Service Applications
    $routes->get('apply-service/(:num)', 'Client\ClientServiceController::applyServiceForm/$1', ['filter' => 'role:4']);
    $routes->post('apply-service/(:num)', 'Client\ClientServiceController::submitServiceApplication/$1', ['filter' => 'role:4']);
    $routes->get('apply-package/(:num)', 'Client\ClientServiceController::applyPackageForm/$1', ['filter' => 'role:4']);
    $routes->post('apply-package/(:num)', 'Client\ClientServiceController::submitPackageApplication/$1', ['filter' => 'role:4']);
    $routes->get('apply-package/submitted/(:num)', 'Client\ClientServiceController::packageSubmitted/$1', ['filter' => 'role:4']);

    // (legacy aliases removed) Use canonical apply-service/apply-package routes instead

    // Notifications
    $routes->get('notification', 'Notifications::index', ['filter' => 'role:4']);
    $routes->post('notification/read', 'Notifications::markRead', ['filter' => 'role:4']);
});

// Registration Journey Routes
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('plan-info', 'Client\ClientRegistrationController::planInfo', ['filter' => 'role:4']);
    $routes->get('plan-registration', 'Client\ClientRegistrationController::planRegistration', ['filter' => 'role:4']);
    $routes->get('plan-registration/(:num)', 'Client\ClientRegistrationController::planRegistration/$1', ['filter' => 'role:4']);
    $routes->post('plan-registration', 'Client\ClientRegistrationController::submitPlanRegistration', ['filter' => 'role:4']);
    $routes->post('plan-registration/(:num)', 'Client\ClientRegistrationController::submitPlanRegistration/$1', ['filter' => 'role:4']);
    
    $routes->get('id-verification-status', 'Client\IdVerificationStatusController::show', ['filter' => 'role:4']);

    $routes->get('initial-payment', 'Client\ClientPaymentInitialController::initialPayment', ['filter' => 'role:4']);
    $routes->post('initial-payment', 'Client\ClientPaymentInitialController::submitInitialPayment', ['filter' => 'role:4']);
    $routes->post('initial-payment-verify/(:num)', 'Client\ClientPaymentInitialController::verifyInitialPayment/$1', ['filter' => 'role:4']);
});
