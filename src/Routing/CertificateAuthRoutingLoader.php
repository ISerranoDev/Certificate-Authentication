<?php

namespace CertificateAuthBundle\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class CertificateAuthRoutingLoader extends Loader
{
    private bool $isLoaded = false;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new \RuntimeException('Do not add the "certificate_auth" loader twice.');
        }

        $routes = new RouteCollection();

        $path = $this->container->getParameter('certificate_auth.login_route_path');
        $name = $this->container->getParameter('certificate_auth.login_route_name');

        $route = new Route($path);
        $route->setDefaults([
            '_controller' => 'certificate_auth.login_controller::login',
        ]);
        $route->setMethods(['GET']);

        $routes->add($name, $route);

        $this->isLoaded = true;

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'certificate_auth';
    }
}
