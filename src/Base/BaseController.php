<?php

namespace App\Base;

use App\Domain\Service\SerializerService;
use App\Extension\AppExtension;
use App\Kernel;
use App\Model\OptionsModel;
use App\Service\OptionBuilder;
use App\Service\UploaderHelper;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\WebpackEncoreBundle\Twig\EntryFilesTwigExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

abstract class BaseController
{
    protected Environment $twig;
    protected ContainerInterface $container;
    protected OptionsModel $options;
    protected SerializerService $serializer;
    protected UploaderHelper $uploader;

    public function __construct()
    {
        date_default_timezone_set('America/Lima');
        setlocale(LC_NUMERIC, 'C');
        $this->container = Kernel::getContainer();

        $loader = new FilesystemLoader('../templates');
        $this->twig = new Environment($loader, [
            'debug' => true,
            'cache' => '../public/tmp/',
        ]);

        $assetsVersion = "v$_ENV[APP_VERSION]";
        $this->twig->addExtension(new AssetExtension(new Packages(new PathPackage('', new StaticVersionStrategy($assetsVersion, '%s?version=%s')))));
        $optionBuilder = $this->container->get(OptionBuilder::class);
        $this->twig->addExtension(new EntryFilesTwigExtension($this->container));
        $this->twig->addExtension(new AppExtension($optionBuilder));

        $this->options = new OptionsModel();
        $this->serializer = $this->container->get(SerializerService::class);
        $this->uploader = $this->container->get(UploaderHelper::class);
    }

    protected function renderView(string $view, array $parameters = []): string
    {
        return $this->twig->render($view, $parameters);
    }

    protected function render(string $view, array $parameters = [], Response $response = null): Response
    {
        $content = $this->renderView($view, $parameters);
        if (null === $response) {
            $response = new Response();
        }

        $response->setContent($content);
        return $response;
    }

    protected function json(mixed $data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        $json = $this->serializer->serialize($data, 'json', array_merge([
            'json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS,
        ], $context));

        return new JsonResponse($json, $status, $headers, true);
    }
}