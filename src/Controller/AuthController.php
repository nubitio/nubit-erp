<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AuthController extends AbstractController
{
    public function __invoke(): never
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the login system.');
    }
}
