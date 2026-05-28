<?php

namespace CertificateAuthBundle\Security;

use CertificateAuthBundle\Transformer\IdentifierTransformerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class CertificateProvider implements UserProviderInterface
{
    private $userRepository;

    public function __construct(
        EntityManagerInterface                             $entityManager,
        private readonly RequestStack                      $requestStack,
        private readonly IdentifierTransformerInterface    $transformer,
        private readonly string                            $userClass,
        private readonly string                            $userIdentifierField,
        private readonly string                            $serialNumberPrefix,
    ) {
        $this->userRepository = $entityManager->getRepository($this->userClass);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $parts = explode(',', $identifier);
        foreach ($parts as $part) {
            $keyValue = explode('=', trim($part), 2);
            if (count($keyValue) === 2 && strcasecmp($keyValue[0], 'SERIALNUMBER') === 0) {
                $nif = str_replace($this->serialNumberPrefix, '', $keyValue[1]);
                $lookupValue = $this->transformer->transform($nif);

                $user = $this->userRepository->findOneBy([$this->userIdentifierField => $lookupValue]);

                if ($user) {
                    $this->requestStack->getSession()->set('auth_cert', true);
                    return $user;
                }
            }
        }

        throw new UserNotFoundException(
            sprintf('No user found for certificate identifier "%s".', $identifier)
        );
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof $this->userClass) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        return $this->loadUserByIdentifier($user->getEmail());
    }

    public function supportsClass(string $class): bool
    {
        return $class === $this->userClass || is_subclass_of($class, $this->userClass);
    }
}
