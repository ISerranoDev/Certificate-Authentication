<?php

namespace CertificateAuthBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class CertificateDataExtractor
{
    public function __construct(
        private readonly string $sslClientVerifyHeader,
        private readonly string $sslClientDnHeader,
        private readonly string $serialNumberPrefix,
        private readonly string $dnSerialField,
        private readonly string $invalidCertificateMessage,
    ) {
    }

    /**
     * Extract the certificate serial number (e.g. NIF) from the request.
     * Returns null if no certificate data is found.
     *
     * @throws CustomUserMessageAuthenticationException if the certificate is present but invalid
     */
    public function extract(Request $request): ?string
    {
        $verifyStatus = $request->server->get($this->sslClientVerifyHeader);

        if ($verifyStatus === null) {
            return null;
        }

        if ($verifyStatus !== 'SUCCESS') {
            throw new CustomUserMessageAuthenticationException($this->invalidCertificateMessage);
        }

        $dn = $request->server->get($this->sslClientDnHeader);
        if ($dn === null) {
            return null;
        }

        $parts = explode(',', $dn);
        foreach ($parts as $part) {
            $keyValue = explode('=', trim($part), 2);
            if (count($keyValue) === 2 && strcasecmp($keyValue[0], $this->dnSerialField) === 0) {
                return str_replace($this->serialNumberPrefix, '', $keyValue[1]);
            }
        }

        return null;
    }
}
