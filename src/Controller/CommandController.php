<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Command;
use App\Entity\CommandItems;
use App\Form\CommandType;
use App\Services\CommandService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/command')]
#[IsGranted("ROLE_USER")]
final class CommandController extends AbstractController
{
    private $entityManager;
    private $commandService;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager, CommandService $commandService, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->commandService = $commandService;
        $this->logger = $logger;
    }
    // Affichage de la commande au paiment

    #[Route('/user', methods: ['GET'])]
    public function user(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();

            $commands = $this->entityManager->getRepository(Command::class)->findOneBy(['user' => $user]);
            if (!$commands) {
                return new JsonResponse(['error' => 'no command user'], Response::HTTP_NO_CONTENT);
            }

            $dataCommands = $serializer->normalize($commands, 'json', ['groups' => ['commands', 'commandItems'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);
            return new JsonResponse($dataCommands, Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('error recovery commands', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Affichage de la liste des commandes d'un utilisateur

    #[Route('/user/list', methods: ['GET'])]
    public function list(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();

            $commands = $this->entityManager->getRepository(Command::class)->findBy(['user' => $user]);
            if (!$commands) {
                return new JsonResponse(['error' => 'no command user'], Response::HTTP_NO_CONTENT);
            }

            $dataCommands = $this->commandService->getCommandData($request, $commands, $serializer);
            return new JsonResponse($dataCommands, Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('error recovery commands', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Récupération d'une commande pour modifier les données utilisateur

    #[Route('/user/{id}', methods: ['GET'])]
    public function currentId(int $id, Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();

            $commands = $this->entityManager->getRepository(Command::class)->findOneBy(['user' => $user, 'id' => $id]);
            if (!$commands) {
                return new JsonResponse(['error' => 'no command user'], Response::HTTP_NO_CONTENT);
            }

            $dataCommand = $this->commandService->getCommandData($request, $commands, $serializer);
            return new JsonResponse($dataCommand, Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('error recovery commands', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Passer une commande utilidateur

    #[Route('/add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            $user = $this->getUser();

            $command = new Command();
            $command->setUser($user);

            $form = $this->createForm(CommandType::class, $command);
            $form->submit($data);
            if ($form->isSubmitted() && $form->isValid()) {
                $cart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
                $items = $cart->getCartItems();
                foreach ($items as $item) {
                    $commandItems = new CommandItems();
                    $commandItems->setProduct($item->getProduct());
                    $commandItems->setTitle($item->getTitle());
                    $commandItems->setPrice($item->getPrice());
                    $commandItems->setQuantity($item->getQuantity());
                    $commandItems->setCommand($command);

                    $command->addCommandItem($commandItems);

                    $this->entityManager->persist($commandItems);
                }

                $this->entityManager->persist($command);

            } else {
                $errors = $this->getErrorMessages($form);
                return new JsonResponse(['errors' => $errors], Response::HTTP_BAD_REQUEST);
            }

            try {
                $this->entityManager->flush();
                return new JsonResponse(['success' => true, 'message' => 'successFully add command user'], Response::HTTP_OK);
            } catch (\Exception $e) {
                $this->logger->error('error add command user', ['error' => $e->getMessage()]);
                return new JsonResponse(['errors' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        } catch(\Throwable $e) {
            $this->logger->error('error add command user', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getErrorMessages(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors() as $key => $error) {
            $errors[] = $error->getMessage();
        }
        foreach ($form->all() as $child) {
            if ($child->isSubmitted() && !$child->isValid()) {
                $errors[$child->getName()] = $this->getErrorMessages($child);
            }
        }
        return $errors;
    }
}
