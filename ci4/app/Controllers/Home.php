<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        return redirect()->to('/');
    }

    public function unauthorized(): string
    {
        return view('auth/unauthorized');
    }
}
