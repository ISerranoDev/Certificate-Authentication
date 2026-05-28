<?php

namespace CertificateAuthBundle\Security;

use CertificateAuthBundle\Transformer\IdentifierTransformerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class CertificateAuthenticator extends AbstractAuthenticator
{
    private $userRepository;

    public function __construct(
        private readonly CertificateDataExtractor         $dataExtractor,
        private readonly RequestStack                     $requestStack,
        private readonly UrlGeneratorInterface             $urlGenerator,
        EntityManagerInterface                             $entityManager,
        private readonly IdentifierTransformerInterface   $transformer,
        private readonly string                           $userClass,
        private readonly string                           $userIdentifierField,
        private readonly string                           $dashboardRoute,
        private readonly string                           $failureRoute,
        private readonly array                            $roleRedirects,
        private readonly array                            $messages,
    ) {
        $this->userRepository = $entityManager->getRepository($this->userClass);
    }

    public function supports(Request $request): ?bool
    {
        return $this->dataExtractor->extract($request) !== null;
    }

    public function authenticate(Request $request): Passport
    {
        $certData = $this->dataExtractor->extract($request);

        if (null === $certData) {
            throw new CustomUserMessageAuthenticationException($this->messages['no_certificate']);
        }

        $lookupValue = $this->transformer->transform($certData);

        $user = $this->userRepository->findOneBy([$this->userIdentifierField => $lookupValue]);

        if (!$user) {
            throw new CustomUserMessageAuthenticationException($this->messages['no_user_found']);
        }

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier()));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        // Create token for the main firewall and clear the cert firewall session
        $mainToken = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $mainToken->setAttribute('certificate-validated', true);

        $session = $this->requestStack->getSession();
        $session->remove('_security_cert');
        $session->set('_security_main', serialize($mainToken));

        // Check role-based redirects
        foreach ($this->roleRedirects as $role => $route) {
            if (method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return new RedirectResponse($this->urlGenerator->generate($route));
            }
        }

        return new RedirectResponse($this->urlGenerator->generate($this->dashboardRoute));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        if ($request->hasSession()) {
            $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        }

        return new RedirectResponse($this->urlGenerator->generate($this->failureRoute));
    }
}
