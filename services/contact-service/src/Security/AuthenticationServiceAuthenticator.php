<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AuthenticationServiceAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(AUTHENTICATION_SERVICE_URL)%')]
        private readonly string $authenticationServiceUrl,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api/');
    }

    public function authenticate(Request $request): Passport
    {
        $token = $request->headers->get('X-AUTH-TOKEN');
        if (null === $token || '' === trim($token)) {
            throw new CustomUserMessageAuthenticationException('Authentication required.');
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                rtrim($this->authenticationServiceUrl, '/').'/api/me',
                [
                    'headers' => ['X-AUTH-TOKEN' => $token],
                    'timeout' => 3,
                ],
            );
            $statusCode = $response->getStatusCode();
            if (Response::HTTP_UNAUTHORIZED === $statusCode || Response::HTTP_FORBIDDEN === $statusCode) {
                throw new CustomUserMessageAuthenticationException('Invalid or expired authentication token.');
            }
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new ServiceUnavailableHttpException(null, 'Authentication service is unavailable.');
            }

            $userData = $response->toArray();
        } catch (CustomUserMessageAuthenticationException|ServiceUnavailableHttpException $e) {
            throw $e;
        } catch (TransportExceptionInterface|DecodingExceptionInterface $e) {
            throw new ServiceUnavailableHttpException(null, 'Authentication service is unavailable.', $e);
        }

        if (!isset($userData['id']) || !is_int($userData['id']) || $userData['id'] < 1) {
            throw new ServiceUnavailableHttpException(null, 'Authentication service returned an invalid user.');
        }

        $user = new ContactUser($userData['id']);

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn (): ContactUser => $user));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => $exception->getMessageKey()], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['error' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
    }
}
