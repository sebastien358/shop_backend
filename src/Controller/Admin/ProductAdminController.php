<?php

namespace App\Controller\Admin;

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
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class ProductAdminController extends AbstractController
{
    private $entityManager;
    private $fileUploader;
    private $productService;
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
    public function list(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $page = $request->query->getInt('page', 1);
            $limit = $request->query->getInt('limit', 20);
            $products = $this->entityManager->getRepository(Product::class)->findAllProductPerPageAdmin($page, $limit);
            if(empty($products)){
                return new JsonResponse(['error' => 'No products found'], Response::HTTP_NO_CONTENT);
            }
            $total = $this->entityManager->getRepository(Product::class)->findAllCountProducts();

            $dataProducts = $this->productService->getProductData($request, $products, $serializer);
            return new JsonResponse(['total' => $total, 'products' => $dataProducts], Response::HTTP_OK);
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
            $data = $request->request->all();
            $form->submit($data);

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

    #[Route('/product/delete/{id}', methods: ['DELETE'])]
    public function deleteProduct(int $id): JsonResponse
    {
        try {
            $product = $this->entityManager->getRepository(Product::class)->find($id);
            if (empty($product)) {
                return new JsonResponse(['error' => 'Product not found'], Response::HTTP_NO_CONTENT);
            }

            foreach ($product->getPictures() as $picture) {
               $fileName = $this->getParameter('images_directory') . '/' . $picture->getFileName();
               if (file_exists($fileName)) {
                   unlink($fileName);
               }
                $this->entityManager->remove($picture);
            }

            $this->entityManager->remove($product);
            
            try {
                $this->entityManager->flush();
                return new JsonResponse(['success' => true, 'success delete product' => Response::HTTP_CREATED]);
            } catch (\Exception $e) {
                $this->logger->error('error recovery products', ['error' => $e->getMessage()]);
                return new JsonResponse(['error' => $e->getMessage(), Response::HTTP_BAD_REQUEST]);
            }

        } catch (\Throwable $e) {
            $this->logger->error('error recovery products', ['error' => $e->getMessage()]);
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
