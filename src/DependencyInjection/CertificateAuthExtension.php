<?php

namespace CertificateAuthBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use CertificateAuthBundle\Security\CertificateAuthenticator;
use CertificateAuthBundle\Security\CertificateChecker;
use CertificateAuthBundle\Security\CertificateProvider;
use CertificateAuthBundle\Security\CertificateDataExtractor;
use CertificateAuthBundle\Controller\CertificateLoginController;
use CertificateAuthBundle\Routing\CertificateAuthRoutingLoader;
use CertificateAuthBundle\Transformer\PlainIdentifierTransformer;

class CertificateAuthExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        // Prepend security configuration so the user doesn't have to
        // manually configure the firewall, provider, etc.
        // This runs BEFORE security extension processes its config,
        // so parameters are not needed — we inject the config directly.

        $configs = $container->getExtensionConfig($this->getAlias());
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->prependExtensionConfig('security', [
            'providers' => [
                'certificate_auth_provider' => [
                    'id' => 'certificate_auth.provider',
                ],
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store config as parameters (only those needed outside this extension)
        $container->setParameter('certificate_auth.login_route_path', $config['login_route_path']);
        $container->setParameter('certificate_auth.login_route_name', $config['login_route_name']);

        // Register identifier transformer: custom service or PlainIdentifierTransformer
        if ($config['identifier_transformer']) {
            $transformerRef = new Reference($config['identifier_transformer']);
        } else {
            $container->register('certificate_auth.plain_transformer', PlainIdentifierTransformer::class);
            $transformerRef = new Reference('certificate_auth.plain_transformer');
        }

        // Register CertificateDataExtractor
        $container->register('certificate_auth.data_extractor', CertificateDataExtractor::class)
            ->setArgument('$sslClientVerifyHeader', $config['ssl_client_verify_header'])
            ->setArgument('$sslClientDnHeader', $config['ssl_client_dn_header'])
            ->setArgument('$serialNumberPrefix', $config['serial_number_prefix'])
            ->setArgument('$dnSerialField', $config['dn_serial_field'])
            ->setArgument('$invalidCertificateMessage', $config['messages']['invalid_certificate'])
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        // Register CertificateAuthenticator
        $container->register('certificate_auth.authenticator', CertificateAuthenticator::class)
            ->setArgument('$dataExtractor', new Reference('certificate_auth.data_extractor'))
            ->setArgument('$requestStack', new Reference('request_stack'))
            ->setArgument('$urlGenerator', new Reference('router'))
            ->setArgument('$entityManager', new Reference('doctrine.orm.entity_manager'))
            ->setArgument('$transformer', $transformerRef)
            ->setArgument('$userClass', $config['user_class'])
            ->setArgument('$userIdentifierField', $config['user_identifier_field'])
            ->setArgument('$dashboardRoute', $config['dashboard_route'])
            ->setArgument('$failureRoute', $config['failure_route'])
            ->setArgument('$roleRedirects', $config['role_redirects'])
            ->setArgument('$messages', $config['messages'])
            ->setAutoconfigured(true)
            ->setAutowired(false)
        ;

        // Register CertificateProvider
        $container->register('certificate_auth.provider', CertificateProvider::class)
            ->setArgument('$entityManager', new Reference('doctrine.orm.entity_manager'))
            ->setArgument('$requestStack', new Reference('request_stack'))
            ->setArgument('$transformer', $transformerRef)
            ->setArgument('$userClass', $config['user_class'])
            ->setArgument('$userIdentifierField', $config['user_identifier_field'])
            ->setArgument('$serialNumberPrefix', $config['serial_number_prefix'])
            ->setAutoconfigured(true)
            ->setAutowired(false)
        ;

        // Register CertificateChecker
        $container->register('certificate_auth.checker', CertificateChecker::class)
            ->setArgument('$checkEnabled', $config['check_user_enabled'])
            ->setArgument('$disabledMessage', $config['user_disabled_message'])
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        // Register Controller
        $container->register('certificate_auth.login_controller', CertificateLoginController::class)
            ->setArgument('$dashboardRoute', $config['dashboard_route'])
            ->setArgument('$failureRoute', $config['failure_route'])
            ->addTag('controller.service_arguments')
            ->setAutoconfigured(true)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        // Register Routing Loader
        $container->register('certificate_auth.routing_loader', CertificateAuthRoutingLoader::class)
            ->setArgument('$container', new Reference('service_container'))
            ->addTag('routing.loader')
        ;
    }

    public function getAlias(): string
    {
        return 'certificate_auth';
    }
}
