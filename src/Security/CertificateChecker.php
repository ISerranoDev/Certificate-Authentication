<?php

namespace CertificateAuthBundle\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class CertificateChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly bool   $checkEnabled,
        private readonly string $disabledMessage,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$this->checkEnabled) {
            return;
        }

        if (method_exists($user, 'isEnabled') && !$user->isEnabled()) {
            throw new CustomUserMessageAccountStatusException($this->disabledMessage);
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Extensible: override this class to add post-auth checks
    }
}
