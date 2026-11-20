<?php

namespace App\Controller;

use App\Entity\User;
use App\Event\UserRegisterEvent;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

#[OA\Tag(name: 'Registration', description: 'User registration and email verification endpoints')]
final class ApiRegisterController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly EmailVerifier $emailVerifier,
    ) {
    }

    #[Route('/api/authenticator/user_register', name: 'app_api_user_register', methods: ['POST'])]
    #[OA\Post(
        operationId: 'registerUser',
        tags: ['Registration'],
        summary: 'Register a new user',
        description: 'Creates the account and sends an email confirmation link.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'fullName'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, maxLength: 4096, example: 'CorrectHorse9!'),
                    new OA\Property(property: 'fullName', type: 'string', example: 'Jane Doe'),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'User created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '0190f3d4-5b6c-7d8e-9f01-2a3b4c5d6e7f'),
                        new OA\Property(property: 'fullName', type: 'string', example: 'Jane Doe'),
                        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Request body is not valid JSON'),
            new OA\Response(
                response: Response::HTTP_CONFLICT,
                description: 'An account with this email already exists',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'An account with this email already exists.')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Invalid email, password or full name'),
        ],
    )]
    public function registerUser(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['message' => 'Request body must be valid JSON.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$payload instanceof \stdClass) {
            return $this->json(['message' => 'Request body must be a JSON object.'], Response::HTTP_BAD_REQUEST);
        }

        $email = is_string($payload->email ?? null) ? strtolower(trim($payload->email)) : '';
        $password = is_string($payload->password ?? null) ? $payload->password : '';
        $fullName = is_string($payload->fullName ?? null) ? $payload->fullName : '';
        if (1 !== preg_match('/^[\p{L}\s\'-]{3,50}$/u', $fullName)) {
            return $this->json([
                'message' => 'Invalid Full Name Format',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($email) > 180
            || strlen($password) < 8
            || strlen($password) > 4096
        ) {
            return $this->json([
                'message' => 'A valid email and a password between 8 and 4096 characters are required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            return $this->json(['message' => 'An account with this email already exists.'], Response::HTTP_CONFLICT);
        }
        $user = new User()->setEmail($email);
        $user->setFullName(trim($fullName));
        $user->setCreatedAt();
        $user->setUpdateAt();
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->dispatcher->dispatch(new UserRegisterEvent($user));
            $this->emailVerifier->sendEmailConfirmation(
                'api_verify_email',
                $user,
                new TemplatedEmail()
                    ->from(new Address('contact@oussemaharrbi.tn', 'oh Mail Bot'))
                    ->to((string) $user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );
        } catch (UniqueConstraintViolationException) {
            return $this->json(['message' => 'An account with this email already exists.'], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'id' => $user->getId()->toRfc4122(),
            'fullName' => $user->getFullName(),
            'email' => $user->getEmail(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/authenticator/verify/email', name: 'api_verify_email')]
    #[OA\Get(
        operationId: 'verifyUserEmail',
        tags: ['Registration'],
        summary: 'Verify a user email address',
        description: 'Follows the signed link sent after registration.',
        parameters: [
            new OA\Parameter(name: 'id', description: 'UUID user ID', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '0190f3d4-5b6c-7d8e-9f01-2a3b4c5d6e7f')),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Email verified',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Your email address has been verified.')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'A valid user id is required'),
            new OA\Response(response: Response::HTTP_CONFLICT, description: 'Invalid or expired verification link'),
        ],
    )]
    public function verifyUserEmail(Request $request): JsonResponse
    {
        $userId = $request->query->get('id');

        if (!is_string($userId) || !Uuid::isValid($userId)) {
            return $this->json([
                'message' => 'verify_email_error A valid user id is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->find(Uuid::fromString($userId));

        if (!$user instanceof User) {
            return $this->json([
                'message' => 'verify_email_error The link to verify your email is invalid. Please request a new link.',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            return $this->json([
                'message' => 'verify_email_error '.$exception->getReason(),
            ], Response::HTTP_CONFLICT);
        }

        return $this->json(['message' => 'Your email address has been verified.'], Response::HTTP_OK);
    }
}
