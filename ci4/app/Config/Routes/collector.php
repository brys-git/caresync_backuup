<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 *
 * Collector Routes.
 *
 * 'dashboard' is role-specific (role_id 5 only) — a dedicated Collector
 * account's own landing page. Everything else under here is a *capability*
 * route, gated by the 'collector' filter (App\Filters\CollectorFilter, via
 * can_collect()) instead of 'role:5' — a Staff account marked
 * users.is_collector reaches these same URLs too, from a link in their own
 * Staff sidebar, without needing a second account or a parallel /staff/...
 * copy of each route.
 */

$routes->group('collector', ['filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('dashboard', 'Dashboard::collector', ['filter' => 'role:5']);

    // "My profile" / "Account settings" - topbar.php and sidebar.php build
    // this URL for every role as "{$cs_role}/profile"; role_id 5 only, same
    // as 'dashboard' above (a Staff account with is_collector uses
    // staff/profile instead, already routed there).
    $routes->get('profile', 'Collector\ProfileController::index', ['filter' => 'role:5']);
    $routes->post('profile/update', 'Collector\ProfileController::update', ['filter' => 'role:5']);

    // Collection List (who has paid, who hasn't) - reachable by role_id 5
    // and by is_collector-flagged Staff alike.
    $routes->get('collection-list', 'CollectionListController::collector', ['filter' => 'collector']);
    $routes->get('commission', 'CollectionListController::commission', ['filter' => 'collector']);
    $routes->get('collection-list/print', 'CollectionListController::printCollector', ['filter' => 'collector']);
    $routes->get('collection-list/record-payment/(:num)', 'CollectionListController::recordPaymentForm/$1', ['filter' => 'collector']);
    $routes->post('collection-list/record-payment/(:num)', 'CollectionListController::submitRecordPayment/$1', ['filter' => 'collector']);
    $routes->post('collection-list/save-remarks/(:num)', 'CollectionListController::saveRemarks/$1', ['filter' => 'collector']);

    // The GCash number/QR that clients in this collector's area are shown
    // on their Make Payment page.
    $routes->get('gcash-account', 'CollectorGcashController::index', ['filter' => 'collector']);
    $routes->post('gcash-account', 'CollectorGcashController::save', ['filter' => 'collector']);

    // GCash approval: the collector assigned to a client's barangay
    // verifies the reference number against their own GCash account and
    // approves/rejects - see PaymentTracking::reviewGcash()'s area-scope
    // check. Path shape (.../approve/$1, .../reject/$1) matches what
    // partials/advance_payment_table.php builds from ap_action_base.
    $routes->post('collection-list/approve/(:num)', 'PaymentTracking::approveGcash/$1', ['filter' => 'collector']);
    $routes->post('collection-list/reject/(:num)', 'PaymentTracking::rejectGcash/$1', ['filter' => 'collector']);
});

// Serves a collector's GCash QR image to signed-in users (a plan holder only
// gets the QR of the collector resolved for their own address - checked in
// the controller, since the filter alone can't express that).
$routes->get('gcash-qr/(:num)', 'CollectorGcashController::qr/$1', ['filter' => 'auth']);
