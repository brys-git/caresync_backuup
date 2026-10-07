<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 * 
 * Staff Routes (Role: 3)
 * All routes require 'auth' and 'role:3' filters
 */

$routes->group('staff', ['filter' => 'auth'], static function (RouteCollection $routes) {
    // Dashboard
    $routes->get('dashboard', 'Dashboard::staff', ['filter' => 'role:3']);

    // Existing member records (past plan, payments, history) - role:3
    $routes->get('existing-records', 'LegacyRecords::index', ['filter' => 'role:3']);
    $routes->get('existing-records/client/(:num)', 'LegacyRecords::workspace/$1', ['filter' => 'role:3']);
    $routes->post('existing-records/client/(:num)/plan', 'LegacyRecords::savePlan/$1', ['filter' => 'role:3']);
    $routes->post('existing-records/client/(:num)/beneficiary', 'LegacyRecords::addBeneficiary/$1', ['filter' => 'role:3']);
    $routes->post('existing-records/client/(:num)/payments', 'LegacyRecords::addPayments/$1', ['filter' => 'role:3']);
    $routes->post('existing-records/client/(:num)/payment/(:num)', 'LegacyRecords::updatePayment/$1/$2', ['filter' => 'role:3']);
    $routes->post('existing-records/client/(:num)/remittance', 'LegacyRecords::addRemittance/$1', ['filter' => 'role:3']);
    $routes->post('existing-records/client/(:num)/service', 'LegacyRecords::addService/$1', ['filter' => 'role:3']);
    $routes->post('existing-records/client/(:num)/document', 'LegacyRecords::addDocument/$1', ['filter' => 'role:3']);
    $routes->post('existing-records/client/(:num)/note', 'LegacyRecords::addNote/$1', ['filter' => 'role:3']);
    $routes->post('existing-records/delete/(:alpha)/(:num)', 'LegacyRecords::delete/$1/$2', ['filter' => 'role:3']);
    $routes->get('existing-records/document/(:num)', 'LegacyRecords::document/$1', ['filter' => 'role:3']);
    $routes->get('existing-records/import', 'LegacyRecords::importForm', ['filter' => 'role:3']);
    $routes->get('existing-records/import/template', 'LegacyRecords::importTemplate', ['filter' => 'role:3']);
    $routes->post('existing-records/import/preview', 'LegacyRecords::importPreview', ['filter' => 'role:3']);
    $routes->post('existing-records/import/commit', 'LegacyRecords::importCommit', ['filter' => 'role:3']);
    $routes->get('existing-records/import/errors/(:segment)', 'LegacyRecords::importErrors/$1', ['filter' => 'role:3']);

    // Create Account (Client only, own branch)
    $routes->get('create-account', 'Staff\AccountController::create', ['filter' => 'role:3']);
    $routes->post('create-account', 'Staff\AccountController::store', ['filter' => 'role:3']);
    $routes->post('client/resend-login/(:num)', 'AccountLoginDetails::resend/$1', ['filter' => 'role:3']);
    $routes->post('client/reinstate/(:num)', 'PlanReinstatement::record/$1', ['filter' => 'role:3']);

    // Client Management
    $routes->get('client-management', 'Staff\ClientController::index', ['filter' => 'role:3']);
    $routes->get('client', 'Staff\ClientController::index', ['filter' => 'role:3']);
    $routes->get('client/view/(:num)', 'ClientDetailController::show/$1', ['filter' => 'role:3']);
    $routes->get('client/statement/(:num)', 'ClientDetailController::statement/$1', ['filter' => 'role:3']);
    $routes->post('client/note/(:num)', 'ClientDetailController::saveNote/$1', ['filter' => 'role:3']);
    $routes->get('client/file/(:num)/(:segment)/(:num)', 'ClientDetailController::file/$1/$2/$3', ['filter' => 'role:3']);
    $routes->get('client/edit/(:num)', 'Staff\ClientController::edit/$1', ['filter' => 'role:3']);
    $routes->post('client/update/(:num)', 'Staff\ClientController::update/$1', ['filter' => 'role:3']);
    $routes->get('client/register', 'Staff\ClientController::create', ['filter' => 'role:3']);
    $routes->post('client/store', 'Staff\ClientController::store', ['filter' => 'role:3']);

    // Government ID manual review queue (no Anthropic API key configured
    // in this environment - every verification needs a human look).
    $routes->get('id-verification', 'IdVerificationQueueController::staff', ['filter' => 'role:3']);
    $routes->get('id-verification/(:num)', 'IdVerificationQueueController::show/$1', ['filter' => 'role:3']);
    $routes->get('id-verification/(:num)/image', 'IdVerificationQueueController::image/$1', ['filter' => 'role:3']);
    $routes->post('id-verification/(:num)/verify', 'IdVerificationQueueController::markVerified/$1', ['filter' => 'role:3']);
    $routes->post('id-verification/(:num)/fail', 'IdVerificationQueueController::markFailed/$1', ['filter' => 'role:3']);

    // Payment Management
    $routes->get('payment-management', 'PaymentTracking::staff', ['filter' => 'role:3']);
    $routes->post('payment-management/record-cash', 'PaymentTracking::recordCash', ['filter' => 'role:3']);
    $routes->post('payment-management/save-remarks/(:num)', 'PaymentTracking::saveRemarks/$1', ['filter' => 'role:3']);

    // Service Management
    $routes->get('services', 'Staff\ServicesController::index', ['filter' => 'role:3']);
    $routes->get('services/ongoing', 'Staff\ServicesController::ongoingServices', ['filter' => 'role:3']);

    // Claims (panel brief section 5/6: claims are processed by Staff/Encoder,
    // not just Branch Admin - see App\Services\ClaimService)
    $routes->get('services/requests', 'Staff\ServiceApplicationController::index', ['filter' => 'role:3']);
    $routes->get('services/requests/(:num)', 'Staff\ServiceApplicationController::show/$1', ['filter' => 'role:3']);
    $routes->get('services/requests/document/(:num)', 'Staff\ServiceApplicationController::downloadDocument/$1', ['filter' => 'role:3']);
    $routes->post('services/requests/approve/(:num)', 'Staff\ServiceApplicationController::approve/$1', ['filter' => 'role:3']);
    $routes->post('services/requests/reject/(:num)', 'Staff\ServiceApplicationController::reject/$1', ['filter' => 'role:3']);

    // Reports & Analytics
    $routes->get('reports', 'Staff\ReportsController::index', ['filter' => 'role:3']);
    $routes->get('analytics', 'Analytics::staff', ['filter' => 'role:3']);

    // Profile Management
    $routes->get('profile', 'Staff\ProfileController::index', ['filter' => 'role:3']);
    $routes->post('profile/update', 'Staff\ProfileController::update', ['filter' => 'role:3']);
});
