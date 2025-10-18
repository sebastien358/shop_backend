<?php

namespace App\Services;

use App\Entity\Picture;
use Doctrine\ORM\EntityManagerInterface;

class ProductService
{
    private $fileUploader;
    private $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager, FileUploader $fileUploader)
    {
        $this->fileUploader = $fileUploader;
        $this->entityManager = $entityManager;
    }

    public function getProductData($request, $products, $serializer)
    {
        if (is_array($products)) {
            $dataProducts = $serializer->normalize($products, 'json', ['groups' => ['products', 'categories', 'pictures'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);

            foreach ($dataProducts as &$product) {
                if (is_array($product['pictures'])) {
                    foreach ($product['pictures'] as &$picture) {
                        if (isset($picture['filename'])) {
                            $picture['filename'] = $request->getSchemeAndHttpHost() . '/images/' . $picture['filename'];
                        }
                    }
                }
            }

            return $dataProducts;

        } else {
            return null;
        }
    }

    public function handleProductImages($request, $product)
    {
        $images = $request->files->get('filename');
        if (!empty($images)) {
            foreach ($images as $image) {
                $fileName = $this->fileUploader->upload($image);
                $picture = new Picture();
                $picture->setFilename($fileName);
                $picture->setProduct($product);
                $this->entityManager->persist($picture);
            }
        }
    }
}
