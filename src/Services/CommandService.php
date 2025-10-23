<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use App\Entity\Command;

class CommandService
{
    public function getCommandData(Request $request, $commands, SerializerInterface $serializer): ?array
    {
        if (is_array($commands)) {

            $dataCommands = $serializer->normalize($commands, 'json', ['groups' =>
                ['commands', 'commandItems', 'products', 'pictures'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);

            $urlFilename = $request->getSchemeAndHttpHost() . '/images/';
            foreach ($dataCommands as &$command) {
                if (!empty($command['commandItems'])) {
                    foreach ($command['commandItems'] as &$commandItem) {
                        foreach ($commandItem['product']['pictures'] as &$picture) {
                            if (!empty($picture['filename'])) {
                                $picture['filename'] = $urlFilename . $picture['filename'];
                            }
                        }
                    }
                } else {
                    $command['commandItems'] = [];
                }
            }

            return $dataCommands;
        } else {
            $dataCommand = $serializer->normalize($commands, 'json', ['groups' => ['commands']]);

            return $dataCommand;
        }
    }
}
