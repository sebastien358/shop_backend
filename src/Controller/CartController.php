<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartItems;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/cart')]
#[IsGranted('ROLE_USER')]
final class CartController extends AbstractController
{
    private $entityManager;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/items/list', methods: ['GET'])]
    public function list(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();

            $cart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (empty($cart)) {
                return new JsonResponse(['message' => 'Cart not found'], Response::HTTP_NOT_FOUND);
            }
            $items = $cart->getCartItems();

            $dataItems = $serializer->normalize($items, 'json', ['groups' => ['cart', 'cart-items', 'products', 'pictures'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);

            $baseUrl = $request->getSchemeAndHttpHost();
            foreach ($dataItems as &$item) {
                if(isset($item['product']['pictures'])) {
                    foreach ($item['product']['pictures'] as &$picture) {
                        if (isset($picture['filename'])) {
                            $picture['filename'] = $baseUrl . '/images/' . $picture['filename'];
                        }
                    }
                }
            }
            return new JsonResponse($dataItems, Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors de la récupération des items du panier', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/to/items', methods: ['POST'])]
    public function cart(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $user = $this->getUser();

            $cart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if(empty($cart)) {
                return new JsonResponse(['message' => 'Cart not found'], Response::HTTP_NOT_FOUND);
            }

            foreach($data as $items) {
                $product = $this->entityManager->getRepository(Product::class)->findOneBy(['id' => $items['id']]);
                if(empty($product)) {
                    return new JsonResponse(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
                }

                $itemToCart = $this->entityManager->getRepository(CartItems::class)->findOneBy(['cart' => $cart, 'product' => $product]);
                if ($itemToCart) {
                    $itemToCart->setQuantity($itemToCart->getQuantity() + $items['quantity']);
                    $this->entityManager->persist($itemToCart);
                } else {
                    $itemToCart = new CartItems();
                    $itemToCart->setCart($cart);
                    $itemToCart->setProduct($product);
                    $itemToCart->setTitle($items['title']);
                    $itemToCart->setPrice($items['price']);
                    $itemToCart->setQuantity($items['quantity']);
                    $this->entityManager->persist($itemToCart);
                }

                try {
                    $this->entityManager->flush();
                    return new JsonResponse(['message' => 'Item added to cart'], Response::HTTP_OK);
                } catch (\Exception $e) {
                    $this->logger->error('Item added to car', ['error' => $e->getMessage()]);
                    return new JsonResponse(['message' => 'Item added to cart'], Response::HTTP_BAD_REQUEST);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Item added to car', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/delete/item/{id}', methods: ['DELETE'])]
    public function deleteItem(Request $request, int $id): JsonResponse
    {
        try {
            $user = $this->getUser();

            $cart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (empty($cart)) {
                return new JsonResponse(['message' => 'Cart not found'], Response::HTTP_NOT_FOUND);
            }

            $itemToCart = $this->entityManager->getRepository(CartItems::class)->findOneBy(['cart' => $cart, 'id' => $id]);

            if ($itemToCart->getQuantity() > 1) {
                $itemToCart->setQuantity($itemToCart->getQuantity() - 1);
                $this->entityManager->persist($itemToCart);
            } else {
                $this->entityManager->remove($itemToCart);
            }

            try {
                $this->entityManager->flush();
                return new JsonResponse(['message' => 'Item removed from cart'], Response::HTTP_OK);
            } catch(\Exception $e) {
                $this->logger->error('Item removed from cart', ['error' => $e->getMessage()]);
                return new JsonResponse(['message' => 'Item removed from cart'], Response::HTTP_BAD_REQUEST);
            }

        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors de la récupération des items du panier', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/add/item/{id}', methods: ['POST'])]
    public function addItem(Request $request, int $id): JsonResponse
    {
        try {
            $user = $this->getUser();

            $cart = $this->entityManager->getRepository(Cart::class)->findOneBy(['user' => $user]);
            if (empty($cart)) {
                return new JsonResponse(['message' => 'Cart not found'], Response::HTTP_NOT_FOUND);
            }

            $itemToCart = $this->entityManager->getRepository(CartItems::class)->findOneBy(['cart' => $cart, 'id' => $id]);
            $itemToCart->setQuantity($itemToCart->getQuantity() + 1);
            $this->entityManager->persist($itemToCart);

            try {
                $this->entityManager->flush();
                return new JsonResponse(['success' => true, 'message' => 'Item added to cart'], Response::HTTP_OK);
            } catch (\Exception $e) {
                $this->logger->error('Item added to cart', ['error' => $e->getMessage()]);
                return new JsonResponse(['message' => 'Item added to cart'], Response::HTTP_BAD_REQUEST);
            }

        } catch(\Throwable $e) {
            $this->logger->error('Error add item to cart', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
