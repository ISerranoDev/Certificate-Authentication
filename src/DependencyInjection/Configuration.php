<?php

namespace CertificateAuthBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('certificate_auth');

        $treeBuilder->getRootNode()
            ->children()
                // Firewall config
                ->scalarNode('firewall_pattern')
                    ->defaultValue('^/certificado')
                    ->info('URL pattern for the certificate firewall')
                ->end()

                // Route config
                ->scalarNode('login_route_path')
                    ->defaultValue('/certificado/iniciar-sesion')
                    ->info('Path for the certificate login route')
                ->end()
                ->scalarNode('login_route_name')
                    ->defaultValue('certificate_auth_login')
                    ->info('Route name for the certificate login')
                ->end()

                // Redirect routes
                ->scalarNode('dashboard_route')
                    ->defaultValue('app_dashboard')
                    ->info('Route to redirect to after successful authentication')
                ->end()
                ->scalarNode('failure_route')
                    ->defaultValue('app_login')
                    ->info('Route to redirect to on authentication failure')
                ->end()

                // Optional role-based redirects
                ->arrayNode('role_redirects')
                    ->useAttributeAsKey('role')
                    ->scalarPrototype()->end()
                    ->info('Map of role => route for role-based redirects after login')
                ->end()

                // User entity config
                ->scalarNode('user_class')
                    ->isRequired()
                    ->info('Fully qualified class name of your User entity')
                ->end()
                ->scalarNode('user_identifier_field')
                    ->defaultValue('nif')
                    ->info('The field in the User entity used to match the certificate serial number')
                ->end()

                // Certificate parsing config
                ->scalarNode('ssl_client_verify_header')
                    ->defaultValue('SSL_CLIENT_VERIFY')
                    ->info('Server variable name for SSL client verification status')
                ->end()
                ->scalarNode('ssl_client_dn_header')
                    ->defaultValue('SSL_CLIENT_S_DN')
                    ->info('Server variable name for SSL client distinguished name')
                ->end()
                ->scalarNode('serial_number_prefix')
                    ->defaultValue('IDCES-')
                    ->info('Prefix to strip from the certificate serial number')
                ->end()
                ->scalarNode('dn_serial_field')
                    ->defaultValue('serialNumber')
                    ->info('Field name in the DN that contains the serial number')
                ->end()

                // Transformer: service ID that implements IdentifierTransformerInterface
                // Allows the user to pass the identifier already encrypted/hashed/normalized
                ->scalarNode('identifier_transformer')
                    ->defaultNull()
                    ->info('Service ID implementing IdentifierTransformerInterface. Transforms the raw certificate identifier before DB lookup. If null, the identifier is used as-is.')
                ->end()

                // User checker
                ->booleanNode('check_user_enabled')
                    ->defaultTrue()
                    ->info('Whether to check if the user is enabled before authentication')
                ->end()
                ->scalarNode('user_disabled_message')
                    ->defaultValue('Tu usuario ha sido desactivado.')
                    ->info('Message shown when a disabled user tries to authenticate')
                ->end()

                // Messages
                ->arrayNode('messages')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('no_certificate')
                            ->defaultValue('No se ha encontrado ningún certificado.')
                        ->end()
                        ->scalarNode('no_user_found')
                            ->defaultValue('No se han encontrado usuarios relacionados con sus certificados.')
                        ->end()
                        ->scalarNode('invalid_certificate')
                            ->defaultValue('El certificado no es válido.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
