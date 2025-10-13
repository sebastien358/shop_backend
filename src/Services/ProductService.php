<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ProductService
{
    public function getProductData($products, $serializer)
    {
        $dataProducts = $serializer->normalize($products, 'json', ['groups' => ['products', 'categories' ,'picture'],
            'circular_reference_handler' => function ($object) {
                return $object->getId();
            }
        ]);

        return $dataProducts;
    }
}
