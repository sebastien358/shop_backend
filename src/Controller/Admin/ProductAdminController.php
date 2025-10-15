<?php

namespace App\Controller\Admin;

use App\Entity\Picture;
use App\Entity\Product;
use App\Form\ProductType;
use App\Services\FileUploader;
use App\Services\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use function Symfony\Component\Translation\t;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class ProductAdminController extends AbstractController
{
    private $entityManager;
    private $productService;
    private $fileUploader;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager, FileUploader $fileUploader, ProductService $productService, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->productService = $productService;
        $this->fileUploader = $fileUploader;
        $this->logger = $logger;
    }

    #[Route('/product/list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        try {

        } catch(\Throwable $e) {
            $this->logger->error('error recovery products', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/product/add', methods: ['POST'])]
    public function addProduct(Request $request): JsonResponse
    {
        try {
            $product = new Product();
            $form = $this->createForm(ProductType::class, $product);
            $form->submit($request->request->all());

            if (!$form->isSubmitted() && !$form->isValid()) {
                $errors = $this->getErrorMessages($form);
                return new JsonResponse(['errors' => $errors], Response::HTTP_BAD_REQUEST);
            }

            $category = $form->get('category')->getData();
            $product->setCategory($category);
            $this->entityManager->persist($category);

            $this->productService->handleProductImages($request, $product);
            $this->entityManager->persist($product);

            try {
                $this->entityManager->flush();
                return new JsonResponse(['success' => true, 'success add product' => Response::HTTP_CREATED]);
            } catch (\Exception $e) {
                $this->logger->error('error recovery products', ['error' => $e->getMessage()]);
                return new JsonResponse(['error' => $e->getMessage(), Response::HTTP_BAD_REQUEST]);
            }

        } catch (\Throwable $e) {
            $this->logger->error('error recovery products', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR]);
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
