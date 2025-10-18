<?php

namespace App\Services;

class CommandService 
{
  public function getCommandData($request, $commands, $serializer)
  {
    if (!empty($commands)) {
      $dataCommands = $serializer->normalize($commands, 'json', [
        'groups' => ['user', 'commands', 'command-items', 'cart', 'cart-items', 'products', 'pictures'],
        'circular_reference_handler' => function ($object) {
          return $object->getId();
        }
      ]);

      $urlFilename = $request->getSchemeAndHttpHost() . '/images/';
      if (isset($dataCommands['user']['cart']['cartItems'])) {
        foreach($dataCommands['user']['cart']['cartItems'] as &$cartItems) {
          if (isset($cartItems['product']['pictures'])) {
            foreach ($cartItems['product']['pictures'] as &$picture) {
              $picture['filename'] = $urlFilename . $picture['filename'];
            }
          }
        }
      }

      return $dataCommands;
    } else {
      return null;
    }
  }
}