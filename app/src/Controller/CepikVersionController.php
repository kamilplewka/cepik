<?php

namespace App\Controller;

use App\Exception\CepikClientException;
use App\Service\CepikClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CepikVersionController
{
    #[Route('/cepik/version', name: 'cepik_version', methods: ['GET'])]
    public function __invoke(CepikClient $cepikClient): JsonResponse
    {
        try {
            $data = $cepikClient->getVersion();
        } catch (CepikClientException $exception) {
            return new JsonResponse([
                'error' => $exception->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse($data);
    }
}
