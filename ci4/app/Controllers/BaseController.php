<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');

        $this->shareCareSyncViewData();
    }

    /**
     * Makes cs_user/cs_branch available to every view rendered by any
     * controller, without editing each controller's view() call.
     *
     * Chosen over a Filter: filters run before the controller method, but
     * the view() call (and its $data array) happens inside the method
     * itself, so a filter has no clean way to inject into it. This works
     * instead because service('renderer') is shared (singleton) per
     * request - CI4's view() helper always fetches that same instance,
     * and View::setData() merges into it rather than replacing - so
     * setting cs_user/cs_branch here, once, before any controller method
     * runs, makes them present in every view() call that follows. A
     * controller that later passes its own cs_user/cs_branch explicitly
     * still wins, since that merge happens after this one.
     */
    private function shareCareSyncViewData(): void
    {
        if (! session()->has('user_id')) {
            return;
        }

        $name = trim((string) session('first_name') . ' ' . (string) session('last_name'));

        $photoUrl = null;
        if ((int) session('role_id') === 4) {
            try {
                $photo = db_connect()->table('users')
                    ->select('profile_photo')
                    ->where('user_id', (int) session('user_id'))
                    ->get()
                    ->getRowArray()['profile_photo'] ?? null;
            } catch (\CodeIgniter\Database\Exceptions\DatabaseException) {
                $photo = null;
            }
            if ($photo !== null && $photo !== '') {
                $photoUrl = base_url('client/profile/photo') . '?v=' . urlencode((string) $photo);
            }
        }

        service('renderer')->setData([
            'cs_user' => [
                'name' => $name,
                'role' => (string) session('role_name'),
                'photo_url' => $photoUrl,
            ],
            'cs_branch' => (string) session('branch_name'),
        ], 'raw');
    }

    /**
     * Helper method: Get a nullable POST value (returns null if empty string)
     */
    protected function nullablePost(string $key): ?string
    {
        // getPost()'s 2nd argument is a sanitize-filter (int|null), not a
        // default value - passing '' throws a TypeError the moment this is
        // actually called with real form data.
        $value = trim((string) $this->request->getPost($key));
        return $value === '' ? null : $value;
    }

    /**
     * Helper method: Get a nullable decimal POST value (returns null if empty or 0.0)
     */
    protected function nullableDecimalPost(string $key): ?float
    {
        // Same fix as nullablePost() above - '' is not a valid $filter arg.
        $value = trim((string) $this->request->getPost($key));
        if ($value === '') {
            return null;
        }
        
        $decimal = (float) $value;
        return $decimal === 0.0 ? null : $decimal;
    }
}
