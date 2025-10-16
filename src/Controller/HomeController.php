<?php

namespace App\Controller;

use App\Entity\Product;
use App\Services\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/product', methods: ['GET'])]
final class HomeController
{
    private $entityManager;
    private $productService;

    public function __construct(EntityManagerInterface $entityManager, ProductService $productService)
    {
        $this->entityManager = $entityManager;
        $this->productService = $productService;
    }

    #[Route('/list', methods: ['GET'])]
    public function list(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $offset = $request->query->getInt('offset', 0);
            $limit = $request->query->getInt('limit', 20);
            $products = $this->entityManager->getRepository(Product::class)->findAllLoadProducts($offset, $limit);
            if (empty($products)) {
                return new JsonResponse(['message' => 'No product'], Response::HTTP_NO_CONTENT);
            }
            $dataProducts = $this->productService->getProductData($request, $products, $serializer);

            return new JsonResponse($dataProducts, Response::HTTP_OK);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $search = $request->query->getString('search');
            $products = $this->entityManager->getRepository(Product::class)->findAllSearch($search);
            $dataProducts = $this->productService->getProductData($request, $products, $serializer);

            return new JsonResponse($dataProducts);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/filtered/price', methods: ['GET'])]
    public function price(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $minPrice = $request->query->getInt('minPrice');
            $maxPrice = $request->query->getInt('maxPrice');
            $products = $this->entityManager->getRepository(Product::class)->findAllPrice($minPrice, $maxPrice);

            $dataProducts = $this->productService->getProductData($request, $products, $serializer);
            return new JsonResponse($dataProducts);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/filtered/category', methods: ['GET'])]
    public function category(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $category = $request->query->getString('category');
            $products = $this->entityManager->getRepository(Product::class)->findAllCategory($category);
            $dataProducts = $this->productService->getProductData($request, $products, $serializer);

            return new JsonResponse($dataProducts);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
