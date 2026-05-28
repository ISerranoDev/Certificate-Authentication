<?php

namespace CertificateAuthBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use CertificateAuthBundle\Security\CertificateAuthenticator;
use CertificateAuthBundle\Security\CertificateChecker;
use CertificateAuthBundle\Security\CertificateProvider;
use CertificateAuthBundle\Security\CertificateDataExtractor;
use CertificateAuthBundle\Controller\CertificateLoginController;
use CertificateAuthBundle\Routing\CertificateAuthRoutingLoader;
use CertificateAuthBundle\Transformer\PlainIdentifierTransformer;

class CertificateAuthExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store config as parameters
        $container->setParameter('certificate_auth.firewall_pattern', $config['firewall_pattern']);
        $container->setParameter('certificate_auth.login_route_path', $config['login_route_path']);
        $container->setParameter('certificate_auth.login_route_name', $config['login_route_name']);
        $container->setParameter('certificate_auth.dashboard_route', $config['dashboard_route']);
        $container->setParameter('certificate_auth.failure_route', $config['failure_route']);
        $container->setParameter('certificate_auth.role_redirects', $config['role_redirects']);
        $container->setParameter('certificate_auth.user_class', $config['user_class']);
        $container->setParameter('certificate_auth.user_identifier_field', $config['user_identifier_field']);
        $container->setParameter('certificate_auth.messages', $config['messages']);

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
