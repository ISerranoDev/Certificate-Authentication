<?php

namespace CertificateAuthBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CertificateLoginController extends AbstractController
{
    public function __construct(
        private readonly string $dashboardRoute,
        private readonly string $failureRoute,
    ) {
    }

    /**
     * This action is the entry point for certificate-based login.
     * The actual authentication is handled by the CertificateAuthenticator.
     * If the user is already logged in, redirect to dashboard.
     * Otherwise, redirect to the standard login page.
     *
     * The route is registered dynamically via the bundle's routing loader.
     */
    public function login(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute($this->dashboardRoute);
        }

        return $this->redirectToRoute($this->failureRoute);
    }
}
