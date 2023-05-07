<?php

namespace App\SandBox;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SandBoxController extends BaseController
{
    #[Route('/sandbox', name: 'sandbox', methods: ['GET'])]
    public function index(CacheInterface $cache): Response
    {
        $content = $cache->get('app.sandbox', function (ItemInterface $item) {
            $item->expiresAfter(60);

            return 'Hello World';
        });

        return $this->render('sandbox/index.html.twig', [
            'content' => $content,
        ]);
    }
}