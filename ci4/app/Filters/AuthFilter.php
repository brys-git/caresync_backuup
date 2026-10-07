<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->has('user_id')) {
            return redirect()->to('/login')->with('error', 'Please sign in first.');
        }

        // $request->getUri()->getPath() returns the path CI4 computed relative
        // to app.baseURL - when the app is served from anywhere that doesn't
        // exactly match baseURL (a different host/port during development,
        // or if baseURL is ever off in a real deployment), it comes back as
        // e.g. "caresync/ci4/public/index.php/change-password" instead of
        // "change-password", so the check below never matches and a user
        // with a temporary password gets redirect-looped on this exact page
        // and can never log in. IncomingRequest::getPath() is the path CI4
        // itself already used to route this request, so it's always right.
        $path = trim(
            $request instanceof \CodeIgniter\HTTP\IncomingRequest ? $request->getPath() : $request->getUri()->getPath(),
            '/'
        );
        $mustChangePassword = (int) session('must_change_password') === 1;
        $allowedPaths = ['change-password', 'logout'];

        if ($mustChangePassword && ! in_array($path, $allowedPaths, true)) {
            return redirect()->to('/change-password')->with('error', 'You must change your temporary password first.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
