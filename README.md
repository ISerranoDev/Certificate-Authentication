# CertificateAuthBundle

Bundle de Symfony para autenticación mediante certificados digitales X.509 (DNIe, FNMT, etc.). Totalmente configurable y sin dependencias externas más allá de Symfony y Doctrine.

## Instalación

```bash
composer require iserrano-dev/certificate-auth-bundle
```

Si usas Symfony Flex, el bundle se registra automáticamente. Si no:

```php
// config/bundles.php
return [
    // ...
    CertificateAuthBundle\CertificateAuthBundle::class => ['all' => true],
];
```

## Configuración

Crea `config/packages/certificate_auth.yaml`:

```yaml
certificate_auth:

    # REQUERIDO: tu clase de entidad User
    user_class: App\Entity\User\User

    # Campo de la entidad para buscar por el serial del certificado (default: nif)
    user_identifier_field: nif

    # Patrón del firewall (default: ^/certificado)
    firewall_pattern: '^/certificado'

    # Ruta y nombre de la ruta de login por certificado
    login_route_path: '/certificado/iniciar-sesion'
    login_route_name: 'certificate_auth_login'

    # Rutas de redirección
    dashboard_route: app_dashboard
    failure_route: app_login

    # Redirecciones por rol (opcional)
    role_redirects:
        ROLE_BASCULISTA: basculista_dis_list
        ROLE_ADMIN: admin_panel

    # Service ID que transforma el identificador antes de buscarlo en BD
    # (ver sección "Transformación del identificador")
    identifier_transformer: null

    # Verificar si el usuario está habilitado (default: true)
    check_user_enabled: true
    user_disabled_message: 'Tu usuario ha sido desactivado.'

    # Headers SSL (configurar según tu servidor web)
    ssl_client_verify_header: SSL_CLIENT_VERIFY
    ssl_client_dn_header: SSL_CLIENT_S_DN

    # Parseo del DN del certificado
    serial_number_prefix: 'IDCES-'
    dn_serial_field: serialNumber

    # Mensajes personalizables
    messages:
        no_certificate: 'No se ha encontrado ningún certificado.'
        no_user_found: 'No se han encontrado usuarios relacionados con sus certificados.'
        invalid_certificate: 'El certificado no es válido.'
```

## Configuración de Security

En `config/packages/security.yaml`:

```yaml
security:
    providers:
        certificate_provider:
            id: certificate_auth.provider

    firewalls:
        cert:
            pattern: '%certificate_auth.firewall_pattern%'
            user_checker: certificate_auth.checker
            x509:
                provider: certificate_provider
                user: "SSL_CLIENT_S_DN"
            custom_authenticators:
                - certificate_auth.authenticator
```

## Registro de rutas

En `config/routes.yaml`:

```yaml
certificate_auth:
    resource: .
    type: certificate_auth
```

## Transformación del identificador

El bundle extrae el número de serie del certificado (por ejemplo, un NIF como `12345678A`) y necesita buscarlo en tu base de datos. El problema es que cada aplicación puede almacenar ese valor de forma diferente: en texto plano, hasheado con SHA-256, encriptado con un servicio propio, etc.

Para resolver esto, el bundle permite configurar un **transformer**: un servicio que recibe el identificador en crudo y devuelve el valor tal como está almacenado en tu BD.

### Sin transformer (por defecto)

Si `identifier_transformer` es `null`, el bundle busca el NIF tal cual en la base de datos. Esto funciona si guardas el NIF en texto plano.

```
Certificado: "12345678A" → BD busca: "12345678A"
```

### Con transformer personalizado

Implementa `IdentifierTransformerInterface`:

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

```
Certificado: "12345678A" → transform() → "a1b2c3..." → BD busca: "a1b2c3..."
```

### Con EncryptBundle

Si usas `iserrano-dev/encrypt-bundle` u otro servicio de encriptación, crea un adaptador:

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

```yaml
certificate_auth:
    identifier_transformer: App\Security\EncryptTransformer
```

### Con cualquier otro servicio

El patrón es siempre el mismo: implementas la interfaz, inyectas lo que necesites, y le dices al bundle qué servicio usar. El bundle no sabe ni le importa cómo transformas el dato.

```php
class HmacTransformer implements IdentifierTransformerInterface
{
    public function __construct(private string $secret) {}

    public function transform(string $identifier): string
    {
        return hash_hmac('sha256', $identifier, $this->secret);
    }
}
```

## Configuración del servidor web

### Nginx + PHP-FPM

```nginx
server {
    listen 443 ssl;

    ssl_certificate         /path/to/server.crt;
    ssl_certificate_key     /path/to/server.key;
    ssl_client_certificate  /path/to/ca-bundle.crt;
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

### Apache

```apache
<Location /certificado>
    SSLVerifyClient optional
    SSLVerifyDepth 2
    SSLOptions +StdEnvVars
</Location>
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

Regístralo en `security.yaml`:

```yaml
firewalls:
    cert:
        user_checker: App\Security\CustomCertificateChecker
```

### Personalizar el extractor de datos del certificado

Si tu certificado tiene un formato de DN diferente, extiende `CertificateDataExtractor`:

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

## Servicios registrados

| Service ID | Clase |
|---|---|
| `certificate_auth.authenticator` | `CertificateAuthenticator` |
| `certificate_auth.provider` | `CertificateProvider` |
| `certificate_auth.checker` | `CertificateChecker` |
| `certificate_auth.data_extractor` | `CertificateDataExtractor` |
| `certificate_auth.login_controller` | `CertificateLoginController` |
| `certificate_auth.plain_transformer` | `PlainIdentifierTransformer` (solo si no se configura `identifier_transformer`) |

## Requisitos

- PHP >= 8.1
- Symfony 6.x o 7.x
- Doctrine ORM
