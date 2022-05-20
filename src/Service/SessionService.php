<?php

namespace App\Service;

class SessionService
{
    public function __construct()
    {
        session_start();
    }

    public function set($key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function get($key)
    {
        return $_SESSION[$key];
    }

    public function delete($key): void
    {
        unset($_SESSION[$key]);
    }
}