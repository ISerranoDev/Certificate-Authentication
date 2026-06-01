# CertificateAuthBundle

Bundle de Symfony para autenticación mediante certificados digitales X.509 (DNIe, FNMT, etc.). Totalmente configurable y sin dependencias externas más allá de Symfony y Doctrine.

## Instalación

```bash
composer require iserranodev/certificate-auth-bundle
```

Al instalar, el bundle crea automáticamente:
- `config/packages/certificate_auth.yaml` — configuración del bundle
- `config/routes/certificate_auth.yaml` — registro de rutas

Si usas Symfony Flex, el bundle se registra automáticamente. Si no, añádelo manualmente:

```php
// config/bundles.php
return [
    // ...
    CertificateAuthBundle\CertificateAuthBundle::class => ['all' => true],
];
```

## Configuración

Edita `config/packages/certificate_auth.yaml` (creado automáticamente):

```yaml
certificate_auth:
    # REQUERIDO: tu clase de entidad User
    user_class: App\Entity\User\User

    # Campo de la entidad para buscar por el serial del certificado (default: nif)
    # user_identifier_field: nif

    # Rutas de redirección (opcionales, tienen valores por defecto)
    # dashboard_route: app_dashboard
    # failure_route: app_login

    # Service ID que transforma el identificador antes de buscarlo en BD
    # identifier_transformer: App\Security\MyTransformer
```

El único parámetro obligatorio es `user_class`. Todo lo demás tiene valores por defecto razonables.

## Configuración automática de Security

El bundle **registra automáticamente** el firewall, el provider y el checker en `security.yaml` mediante `PrependExtensionInterface`. No necesitas añadir nada manualmente en `security.yaml`.

El bundle inyecta esta configuración:

```yaml
# Esto lo hace el bundle automáticamente, NO lo añadas tú
security:
    providers:
        certificate_auth_provider:
            id: certificate_auth.provider
    firewalls:
        certificate_auth:
            pattern: ^/certificado
            user_checker: certificate_auth.checker
            custom_authenticators:
                - certificate_auth.authenticator
```

Si necesitas personalizar el firewall (por ejemplo, cambiar el pattern), puedes sobreescribirlo en tu propio `security.yaml`, ya que la configuración del bundle se inyecta con `prependExtensionConfig` (menor prioridad que tu config).

## Configuración completa (referencia)

```yaml
certificate_auth:
    # REQUERIDO
    user_class: App\Entity\User\User

    # Campo de búsqueda (default: nif)
    user_identifier_field: nif

    # Ruta de login por certificado
    login_route_path: '/certificado/iniciar-sesion'
    login_route_name: 'certificate_auth_login'

    # Rutas de redirección
    dashboard_route: app_dashboard
    failure_route: app_login

    # Redirecciones por rol
    role_redirects:
        ROLE_BASCULISTA: basculista_dis_list
        ROLE_ADMIN: admin_panel

    # Transformer del identificador
    identifier_transformer: null

    # Headers SSL
    ssl_client_verify_header: SSL_CLIENT_VERIFY
    ssl_client_dn_header: SSL_CLIENT_S_DN

    # Parseo del DN
    serial_number_prefix: 'IDCES-'
    dn_serial_field: serialNumber

    # Verificación de usuario
    check_user_enabled: true
    user_disabled_message: 'Tu usuario ha sido desactivado.'

    # Mensajes
    messages:
        no_certificate: 'No se ha encontrado ningún certificado.'
        no_user_found: 'No se han encontrado usuarios relacionados con sus certificados.'
        invalid_certificate: 'El certificado no es válido.'
```

## Transformación del identificador

Por defecto, el bundle busca el identificador del certificado (NIF, etc.) directamente en la base de datos sin transformarlo.

### Con transformer personalizado

```php
namespace App\Security;

use CertificateAuthBundle\Transformer\IdentifierTransformerInterface;

class Sha256Transformer implements IdentifierTransformerInterface
{
    public function transform(string $identifier): string
    {
        return hash('sha256', $identifier);
    }
}
```

```yaml
certificate_auth:
    identifier_transformer: App\Security\Sha256Transformer
```

### Con EncryptBundle u otro servicio

```php
namespace App\Security;

use CertificateAuthBundle\Transformer\IdentifierTransformerInterface;
use ISerranoDev\EncryptBundle\Service\EncryptService;

class EncryptTransformer implements IdentifierTransformerInterface
{
    public function __construct(
        private readonly EncryptService $encryptService
    ) {}

    public function transform(string $identifier): string
    {
        return $this->encryptService->hashData($identifier);
    }
}
```

## Configuración del servidor web

### Apache

```apache
SSLCACertificateFile /path/to/FNMT_CA_bundle.crt

<Location /certificado/iniciar-sesion>
    SSLVerifyClient require
    SSLVerifyDepth 5
</Location>

<Location /cerrar-sesion>
    SSLVerifyClient none
</Location>
```

### Nginx + PHP-FPM

```nginx
server {
    listen 443 ssl;

    ssl_client_certificate  /path/to/FNMT_CA_bundle.crt;
    ssl_verify_client       optional;

    location /certificado {
        fastcgi_param SSL_CLIENT_VERIFY $ssl_client_verify;
        fastcgi_param SSL_CLIENT_S_DN   $ssl_client_s_dn;

        fastcgi_pass unix:/run/php/php-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    }
}
```

## Extensibilidad

### Personalizar el UserChecker

```php
namespace App\Security;

use CertificateAuthBundle\Security\CertificateChecker as BaseChecker;
use Symfony\Component\Security\Core\User\UserInterface;

class CustomCertificateChecker extends BaseChecker
{
    public function checkPreAuth(UserInterface $user): void
    {
        parent::checkPreAuth($user);
        // Tu lógica adicional...
    }
}
```

### Personalizar el extractor de datos del certificado

```php
namespace App\Security;

use CertificateAuthBundle\Security\CertificateDataExtractor as BaseExtractor;
use Symfony\Component\HttpFoundation\Request;

class CustomDataExtractor extends BaseExtractor
{
    public function extract(Request $request): ?string
    {
        // Tu lógica personalizada
    }
}
```

### Sobreescribir el firewall

Si el pattern `^/certificado` no te sirve, simplemente define tu firewall en `security.yaml` con la misma key `certificate_auth` y tu config tendrá prioridad:

```yaml
security:
    firewalls:
        certificate_auth:
            pattern: ^/mi-ruta-custom
            user_checker: certificate_auth.checker
            custom_authenticators:
                - certificate_auth.authenticator
```

## Servicios registrados

| Service ID | Clase |
|---|---|
| `certificate_auth.authenticator` | `CertificateAuthenticator` |
| `certificate_auth.provider` | `CertificateProvider` |
| `certificate_auth.checker` | `CertificateChecker` |
| `certificate_auth.data_extractor` | `CertificateDataExtractor` |
| `certificate_auth.login_controller` | `CertificateLoginController` |

## Requisitos

- PHP >= 8.1
- Symfony 6.x o 7.x
- Doctrine ORM
