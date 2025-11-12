<?php

namespace App\Core\Base;

use App\Core\Service\SerializerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class BaseController extends AbstractController
{
    protected SerializerService $serializer;

    public function __construct()
    {
        $this->serializer = new SerializerService();
    }
}
