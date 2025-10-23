<?php

namespace App\Controller;

use Stripe\Stripe;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PaymentController extends AbstractController
{
    #[Route('/api/payment', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request, string $STRIPE_SECRET_KEY, LoggerInterface $logger): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            
            $token = $data['token'] ?? null;
            $price = $data['price'] ?? null;

            if (!$price || !$token) {
                return new JsonResponse(['error' => 'Token et prix sont requis'], Response::HTTP_BAD_REQUEST);
            }

            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Utilisateur non connecté'], Response::HTTP_UNAUTHORIZED);
            }

            Stripe::setApiKey($STRIPE_SECRET_KEY);
            $charge = \Stripe\Charge::create([
                'amount' => $price * 100, // Montant à débiter (en centimes)
                'currency' => 'eur', // Devise
                'source' => $token,
            ]);

            return new JsonResponse(['success' => true, 'message' => 'Paiement accepté'], Response::HTTP_ACCEPTED);
        } catch (\Throwable $e) {
            $logger->error('Erreur de paiement', ['message' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

