<?php

namespace CertificateAuthBundle\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvents;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;

class ConfigPlugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'onPackageInstall',
            PackageEvents::POST_PACKAGE_UPDATE => 'onPackageUpdate',
        ];
    }

    public function onPackageInstall(PackageEvent $event): void
    {
        $operation = $event->getOperation();
        if ($operation instanceof InstallOperation && $this->isThisPackage($operation->getPackage()->getName())) {
            $this->createConfigFiles();
        }
    }

    public function onPackageUpdate(PackageEvent $event): void
    {
        $operation = $event->getOperation();
        if ($operation instanceof UpdateOperation && $this->isThisPackage($operation->getTargetPackage()->getName())) {
            $this->createConfigFiles();
        }
    }

    private function isThisPackage(string $packageName): bool
    {
        return $packageName === 'iserrano-dev/certificate-auth-bundle';
    }

    private function createConfigFiles(): void
    {
        // Detect project root (where composer.json lives)
        $projectDir = getcwd();

        // Only create files if it looks like a Symfony project
        if (!is_dir($projectDir . '/config')) {
            return;
        }

        $this->createFileIfNotExists(
            $projectDir . '/config/packages/certificate_auth.yaml',
            $this->getConfigContent()
        );

        $this->createFileIfNotExists(
            $projectDir . '/config/routes/certificate_auth.yaml',
            $this->getRoutesContent()
        );
    }

    private function createFileIfNotExists(string $path, string $content): void
    {
        if (file_exists($path)) {
            $this->io->write(sprintf('  <comment>Skipped (already exists):</comment> %s', $this->relativePath($path)));
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $content);
        $this->io->write(sprintf('  <info>Created:</info> %s', $this->relativePath($path)));
    }

    private function relativePath(string $path): string
    {
        $cwd = getcwd();
        if (str_starts_with($path, $cwd)) {
            return ltrim(substr($path, strlen($cwd)), '/');
        }
        return $path;
    }

    private function getConfigContent(): string
    {
        return <<<'YAML'
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
    }

    private function getRoutesContent(): string
    {
        return <<<'YAML'
certificate_auth:
    resource: .
    type: certificate_auth
 
YAML;
    }
}