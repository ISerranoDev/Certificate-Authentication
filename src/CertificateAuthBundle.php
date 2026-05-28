<?php

namespace CertificateAuthBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use CertificateAuthBundle\DependencyInjection\CertificateAuthExtension;

class CertificateAuthBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): CertificateAuthExtension
    {
        return new CertificateAuthExtension();
    }
}
