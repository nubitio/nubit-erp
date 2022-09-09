<?php

namespace App;


use App\Domain\Annotation\IgnoreAutowire;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

#[IgnoreAutowire]
class Kernel extends HttpKernel
{
    private static ContainerInterface $container;

    public function __construct($routes, ContainerInterface $container)
    {
        self::$container = $container;
        $context = new RequestContext();
        $matcher = new UrlMatcher($routes, $context);
        $requestStack = new RequestStack();

        $controllerResolver = new ControllerResolver();
        $argumentResolver = new ArgumentResolver();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RouterListener($matcher, $requestStack));
        $dispatcher->addSubscriber(new ResponseListener('UTF-8'));

        parent::__construct($dispatcher, $controllerResolver, $requestStack, $argumentResolver);
    }

    public static function getContainer(): ContainerInterface
    {
        return self::$container;
    }
}