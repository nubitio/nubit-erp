<?php

declare(strict_types=1);

namespace App\Controller;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AuthController extends AbstractController
{
    public function __invoke(): never
    {
        throw new LogicException('This method can be blank - it will be intercepted by the login system.');
    }
}
