<?php

namespace CertificateAuthBundle\Transformer;

/**
 * Default transformer: returns the identifier unchanged.
 * Used when no identifier_transformer is configured.
 */
class PlainIdentifierTransformer implements IdentifierTransformerInterface
{
    public function transform(string $identifier): string
    {
        return $identifier;
    }
}
