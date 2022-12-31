<?php

namespace App\Base;

use App\Core\Infrastructure\Service\SerializerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class BaseController extends AbstractController
{
    protected SerializerService $serializer;

    public function __construct()
    {
        $this->serializer = new SerializerService();
    }
}
