<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 * 
 * System Admin Routes (Role: 1)
 * All routes require 'auth' and 'role:1' filters
 */

$routes->group('admin', ['filter' => 'auth'], static function (RouteCollection $routes) {
    // Dashboard
    $routes->get('dashboard', 'Dashboard::admin', ['filter' => 'role:1']);

    // Registration & Approvals
    $routes->get('registration-approvals', 'ClientPortal::registrationApprovals', ['filter' => 'role:1']);

    // Branch Management
    $routes->get('branch-management', 'Admin\BranchManagementController::index', ['filter' => 'role:1']);
    $routes->post('branch-management/toggle-availability', 'Admin\BranchManagementController::toggleAvailability', ['filter' => 'role:1']);
    $routes->get('branch-management/export-transactions', 'Admin\BranchManagementController::exportTransactions', ['filter' => 'role:1']);
    $routes->post('branch-management/approval/service/approve/(:num)', 'Admin\BranchManagementController::approveService/$1', ['filter' => 'role:1']);
    $routes->post('branch-management/approval/service/reject/(:num)', 'Admin\BranchManagementController::rejectService/$1', ['filter' => 'role:1']);
    $routes->post('branch-management/approval/package/approve/(:num)', 'Admin\BranchManagementController::approvePackage/$1', ['filter' => 'role:1']);
    $routes->post('branch-management/approval/package/reject/(:num)', 'Admin\BranchManagementController::rejectPackage/$1', ['filter' => 'role:1']);

    // Client Management
    $routes->get('client-management', 'Admin\ClientManagementController::index', ['filter' => 'role:1']);
    $routes->get('client-management/view/(:num)', 'ClientDetailController::show/$1', ['filter' => 'role:1']);
    $routes->get('client-management/statement/(:num)', 'ClientDetailController::statement/$1', ['filter' => 'role:1']);
    $routes->post('client-management/note/(:num)', 'ClientDetailController::saveNote/$1', ['filter' => 'role:1']);
    $routes->get('client-management/file/(:num)/(:segment)/(:num)', 'ClientDetailController::file/$1/$2/$3', ['filter' => 'role:1']);
    $routes->get('client-management/edit/(:num)', 'Admin\ClientManagementController::edit/$1', ['filter' => 'role:1']);
    $routes->post('client-management/update/(:num)', 'Admin\ClientManagementController::update/$1', ['filter' => 'role:1']);
    $routes->get('client/register', 'Admin\ClientManagementController::create', ['filter' => 'role:1']);
    $routes->post('client/store', 'Admin\ClientManagementController::store', ['filter' => 'role:1']);

    // Create Account (Branch Admin / Staff / Client)
    $routes->get('create-account', 'Admin\AccountController::create', ['filter' => 'role:1']);
    $routes->post('create-account', 'Admin\AccountController::store', ['filter' => 'role:1']);

    $routes->post('client-management/resend-login/(:num)', 'AccountLoginDetails::resend/$1', ['filter' => 'role:1']);
    $routes->post('client-management/reinstate/(:num)', 'PlanReinstatement::record/$1', ['filter' => 'role:1']);

    // Payment Monitoring
    $routes->get('payment-monitoring', 'PaymentTracking::admin', ['filter' => 'role:1']);
    $routes->get('payment-monitoring/export', 'PaymentTracking::exportCsv', ['filter' => 'role:1']);

    // Reports & Analytics (panel brief section 7)
    $routes->get('reports', 'Reports::index', ['filter' => 'role:1']);
    $routes->get('reports/overdue', 'Reports::overdue', ['filter' => 'role:1']);
    $routes->get('reports/ledger', 'Reports::ledger', ['filter' => 'role:1']);
    $routes->get('reports/collections', 'Reports::collections', ['filter' => 'role:1']);
    $routes->get('reports/commission', 'Reports::commission', ['filter' => 'role:1']);
    $routes->get('reports/remittance', 'Reports::remittance', ['filter' => 'role:1']);
    $routes->get('analytics', 'Analytics::admin', ['filter' => 'role:1']);

    // Service Offer Management
    $routes->get('service-offer', 'Admin\ServiceOfferController::index', ['filter' => 'role:1']);
    $routes->post('service-offer/package/store', 'Admin\ServiceOfferController::storePackage', ['filter' => 'role:1']);
    $routes->post('service-offer/service/store', 'Admin\ServiceOfferController::storeService', ['filter' => 'role:1']);
    $routes->post('service-offer/approval/service/approve/(:num)', 'Admin\ServiceOfferController::approveService/$1', ['filter' => 'role:1']);
    $routes->post('service-offer/approval/service/reject/(:num)', 'Admin\ServiceOfferController::rejectService/$1', ['filter' => 'role:1']);
    $routes->post('service-offer/approval/package/approve/(:num)', 'Admin\ServiceOfferController::approvePackage/$1', ['filter' => 'role:1']);
    $routes->post('service-offer/approval/package/reject/(:num)', 'Admin\ServiceOfferController::rejectPackage/$1', ['filter' => 'role:1']);

    // Profile Management
    $routes->get('profile', 'Admin\ProfileController::index', ['filter' => 'role:1']);
    $routes->get('profile/edit', 'Admin\ProfileController::edit', ['filter' => 'role:1']);
    $routes->post('profile/update', 'Admin\ProfileController::update', ['filter' => 'role:1']);
    $routes->post('profile/change-password', 'Admin\ProfileController::changePassword', ['filter' => 'role:1']);
});
