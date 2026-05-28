<?php

namespace CertificateAuthBundle\Installer;

use Composer\Script\Event;

class ConfigInstaller
{
    private const CONFIG_FILE = 'config/packages/certificate_auth.yaml';
    private const ROUTES_FILE = 'config/routes/certificate_auth.yaml';

    private const CONFIG_CONTENT = <<<'YAML'
certificate_auth:
    # REQUIRED: Your User entity class
    user_class: App\Entity\User

    # Field in the User entity to match the certificate serial number
    # user_identifier_field: nif

    # Routes for redirect after login
    # dashboard_route: app_dashboard
    # failure_route: app_login

    # Service ID implementing IdentifierTransformerInterface
    # to transform the identifier before DB lookup (hash, encrypt, etc.)
    # identifier_transformer: App\Security\MyTransformer

YAML;

    private const ROUTES_CONTENT = <<<'YAML'
certificate_auth:
    resource: .
    type: certificate_auth

YAML;

    public static function install(Event $event): void
    {
        $io = $event->getIO();
        $projectDir = getcwd();

        self::createFileIfNotExists($projectDir . '/' . self::CONFIG_FILE, self::CONFIG_CONTENT, $io);
        self::createFileIfNotExists($projectDir . '/' . self::ROUTES_FILE, self::ROUTES_CONTENT, $io);
    }

    private static function createFileIfNotExists(string $path, string $content, $io): void
    {
        if (file_exists($path)) {
            $io->write(sprintf('  <comment>File already exists:</comment> %s', $path));
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $content);
        $io->write(sprintf('  <info>Created:</info> %s', $path));
    }
}
