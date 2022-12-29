<?php

namespace App\Service;

class SessionService
{
    public function __construct()
    {
        if (PHP_SESSION_NONE == session_status()) {
            session_start();
        }
    }

    public function set($key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function get($key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function delete($key): void
    {
        unset($_SESSION[$key]);
    }
}
