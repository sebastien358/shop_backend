<?php

namespace App\Controller;

use DateTime;
use App\Entity\User;
use App\Services\MailerProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/user', name: 'user')]
final class UserController extends AbstractController
{
    private $entityManager;
    private $passwordHasher;
    private $mailerProvider;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher,
        MailerProvider $mailerProvider, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->mailerProvider = $mailerProvider;
        $this->logger = $logger;
    }

    #[Route('/me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();
            $dataUser = $serializer->normalize($user, 'json', ['groups' => ['user']]);
        } catch(\Throwable $e) {
            $this->logger->error('Something went wrong', ['Error' => $e->getMessage()]);
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse($dataUser, Response::HTTP_ACCEPTED);
    }

    #[Route('/email/existing', methods: ['POST'])]
    public function emailExisting(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            $email = $data['email'] ?? null;
            if (!$email) {
                return new JsonResponse(['error' => 'user no exists'], Response::HTTP_NOT_FOUND);
            }

            $emailExists = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($emailExists) {
                return new JsonResponse(['exists' => true, 'message' => 'Email already exists'], Response::HTTP_ACCEPTED);
            } else {
                return new JsonResponse(['exists' => false, 'message' => 'Email does not exist'], Response::HTTP_ACCEPTED);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error email existing user', ['error' => $e->getMessage()]);
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/request-password', methods: ['POST'])]
    public function emailPassword(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $dataEmail = $data['email'] ?? null;

            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $dataEmail]);
            if (!$user) {
                return new JsonResponse(['error' => 'user no exists'], Response::HTTP_NOT_FOUND);
            }

            $token = bin2hex(random_bytes(32));
            $user->setResetToken($token);
            $user->setResetTokenExpiresAt(new DateTime('+1 hour'));
            $this->entityManager->persist($user);

            try {
                $url = $this->getParameter('frontend_url') . '/reset-password/' . $token;

                $body = $this->render('emails/reset-password.html.twig', [
                    'url' => $url
                ])->getContent();
                $this->mailerProvider->sendEmail($user->getEmail(),'Demande de réinitialisation de mot de passe' , $body);

                $this->entityManager->flush();
                return new JsonResponse(['success' => true, 'message' => 'Un email a été envoyé'], Response::HTTP_ACCEPTED);
            } catch(\Exception $e) {
                $this->logger->error('Error email existing user', ['error' => $e->getMessage()]);
                return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error email existing user', ['error' => $e->getMessage()]);
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/reset-password/{token}', methods: ['POST'])]
    public function resetPassword(Request $request, string $token): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $newPassword = $data['password'] ?? null;

            if (!$newPassword) {
                return new JsonResponse(['error' => 'New password no exists'], Response::HTTP_NOT_FOUND);
            }

            $user = $this->entityManager->getRepository(User::class)->findOneBy(['resetToken' => $token]);
            if(!$user) {
                return new JsonResponse(['error' => 'User no exists'], Response::HTTP_NOT_FOUND);
            }

            if ($user->getResetTokenExpiresAt() < new DateTime()) {
                return new JsonResponse(['error' => 'Token expired'], Response::HTTP_FORBIDDEN);
            }

            $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));

            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $this->entityManager->persist($user);

            try {
                $this->entityManager->flush();
                return new JsonResponse(['success' => true, 'message' => 'Password modified'], Response::HTTP_ACCEPTED);
            } catch(\Exception $e) {
                $this->logger->error('Error reset password', ['error' => $e->getMessage()]);
                return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error token existing user', ['error' => $e->getMessage()]);
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
