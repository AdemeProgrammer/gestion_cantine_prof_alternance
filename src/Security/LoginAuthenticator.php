<?php

namespace App\Security;

use App\Repository\PromoRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private PromoRepository $promoRepo
    ) {}

    public function authenticate(Request $request): Passport
    {
        $email = $request->getPayload()->getString('email');
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->getPayload()->getString('password')),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // 1) Si une page protégée avait été demandée avant login, on y retourne
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            if ($this->isSafeInternalPath($targetPath)) {
                // Cas particulier: /cal/month/{id} -> vérifier que la promo existe encore
                $path = parse_url($targetPath, PHP_URL_PATH) ?? '';
                if (preg_match('#^/cal/month/(\d+)\b#', $path, $m)) {
                    $promoId = (int) $m[1];
                    if (!$this->promoRepo->find($promoId)) {
                        // Promo absente (ex: reset BDD) => on ignore ce target path
                        return new RedirectResponse($this->urlGenerator->generate('app_promo_index'));
                    }
                }
                return new RedirectResponse($targetPath);
            }
        }

        // 2) Paramètre ?next=/chemin (ou champ hidden "next")
        $next = $request->query->get('next', '');
        if ($next === '') {
            $next = $request->getPayload()->getString('next', '');
        }
        if (is_string($next) && $this->isSafeInternalPath($next)) {
            return new RedirectResponse($next);
        }

        // 3) Fallback final: index des promos
        return new RedirectResponse($this->urlGenerator->generate('app_promo_index'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    private function isSafeInternalPath(?string $url): bool
    {
        if (!$url) return false;
        // pas d'URL absolue externe
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) return false;
        // doit commencer par un chemin interne
        return str_starts_with($url, '/');
    }
}
