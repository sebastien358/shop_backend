<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;


#[Route('/api/user', name: 'user')]
final class UserController extends AbstractController
{
    private $entityManager;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
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

        return new JsonResponse($dataUser, 200);
    }

    #[Route('/email/existing', methods: ['POST'])]
    public function emailExisting(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $emailExisting = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);

            if ($emailExisting) {
                return new JsonResponse(['exists' => true, 'message' => 'Email already exists'], 200);
            } else {
                return new JsonResponse(['exists' => false, 'message' => 'Email does not exist'], 200);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error email existing user', ['error' => $e->getMessage()]);
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
