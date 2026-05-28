<?php

namespace CertificateAuthBundle\Transformer;

/**
 * Transforms the raw certificate identifier before database lookup.
 *
 * Implement this interface to match how your application stores
 * the identifier in the database. For example:
 *
 *  - Hashed with SHA-256
 *  - Encrypted with a custom service
 *  - Normalized (uppercase, trimmed, etc.)
 *
 * Register your implementation as a service and configure it:
 *
 *     certificate_auth:
 *         identifier_transformer: App\Security\MyTransformer
 */
interface IdentifierTransformerInterface
{
    /**
     * Transform the raw identifier extracted from the certificate
     * into the value that will be used to query the database.
     *
     * @param string $identifier The raw identifier (e.g. NIF: "12345678A")
     * @return string The transformed value as stored in the database
     */
    public function transform(string $identifier): string;
}
