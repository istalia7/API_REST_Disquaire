<?php

namespace App\Controller;

use App\Entity\Singer;
use App\Repository\SingerRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use OpenApi\Attributes as OA;

class SingerController extends AbstractController
{
    #[OA\Get(
        path: "/api/singers",
        summary: "Cette méthode permet de Récupérer tous les chanteurs",
        parameters: [
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), description: "La page que l'on veut récupérer"),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer"), description: "Le nombre de chanteurs que l'on veut récupérer par page")
        ],
        responses: [
            new OA\Response(response: 200, description: "Retourne la liste des chanteurs", content: new OA\JsonContent(type: "array", items: new OA\Items(ref: new Model(type: Singer::class, groups: ["getSingers"]))))
        ],
        tags: ["Singers"]
    )]
    #[Route('/api/singers', name: 'singer', methods: ['GET'])]
    public function getAllSingers(SingerRepository $singerRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 5);
        $idCache = "getAllSingers-" . $page . "-" . $limit;
        $context = SerializationContext::create()->setGroups(['getSingers']);
        $jsonSingerList = $cache->get($idCache, function (ItemInterface $item) use ($singerRepository, $page, $limit, $serializer, $context) {
            $item->tag("recordsCache");
            $item->expiresAfter(120);
            $singerList = $singerRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($singerList, 'json', $context);
        });
        return new JsonResponse($jsonSingerList, Response::HTTP_OK, [], true);
    }

    #[OA\Get(
        path: "/api/singer/{id}",
        summary: "Cette méthode permet de récupérer les détails d'un chanteur",
        parameters: [
            new OA\Parameter(name: "id", in: "path", schema: new OA\Schema(type: "integer"), description: "L'id du chanteur que l'on veut récupérer")
        ],
        responses: [
            new OA\Response(response: 200, description: "Retourne les détails d'un chanteur", content: new OA\JsonContent(ref: new Model(type: Singer::class, groups: ["getSingers"])))
        ],
        tags: ["Singers"]

    )]
    #[Route('/api/singer/{id}', name: 'singerDetail', methods: ['GET'])]
    public function getSingerDetail(Singer $singer, SerializerInterface $serializer): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(["getSingers", "getSingerRecord", "getSongAlbum"]);
        $jsonSinger = $serializer->serialize($singer, 'json', $context);
        return new JsonResponse($jsonSinger, RESPONSE::HTTP_OK, ['accept' => 'json'], true);
    }

    #[OA\Delete(
        path: "/api/singer/{id}",
        summary: "Cette méthode permet de supprimer un chanteur",
        parameters: [
            new OA\Parameter(name: "id", in: "path", schema: new OA\Schema(type: "integer", description: "L'id du chanteur à supprimer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Chanteur supprimé")
        ],
        tags: ["Singers"]
    )]
    #[Route('/api/singer/{id}', name: 'deleteSinger', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour supprimer un chanteur")]
    public function deleteSinger(Singer $singer, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(["singersCache"]);
        $em->remove($singer);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Post(
        path: "/api/singers",
        summary: "Cette méthode permet de créer un chanteur",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['firstName', 'lastName'],
                    properties: [
                        new OA\Property(property: 'firstName', type: 'string', example: 'PrénomChanteur'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'NomChanteur')
                    ]
                )
            )
        ),
        tags: ["Singers"]
    )]
    #[Route('/api/singers', name: 'createSinger', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour créer un chanteur")]
    public function createSinger(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): JsonResponse
    {
        $singer = $serializer->deserialize($request->getContent(), Singer::class, 'json');

        $errors = $validator->validate($singer);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($singer);
        $em->flush();
        $context = SerializationContext::create()->setGroups(["getSingers"]);
        $jsonSinger = $serializer->serialize($singer, 'json', $context);

        $location = $urlGenerator->generate('singerDetail', ['id' => $singer->getId()], UrlGeneratorInterface::ABSOLUTE_PATH);

        return new JsonResponse($jsonSinger, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[OA\Put(
        path: "/api/singer/{id}",
        summary: "Cette méthode permet de mettre à jour un disque",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(
                    type: "object",
                    required: ["firstName", "lastName"],
                    properties: [
                        new OA\Property(property: 'firstName', type: 'string', example: 'PrénomChanteur'),
                        new OA\Property(property: 'lastName', type: 'decimal', example: 'NomChanteur')
                    ]
                )
            )
        ),
        tags: ["Singers"]
    )]
    #[Route('/api/singer/{id}', name: 'updateSinger', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour mettre à jour un chanteur")]
    public function updateSinger(Request $request, SerializerInterface $serializer, Singer $currentSinger, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $newSinger = $serializer->deserialize($request->getContent(), Singer::class, 'json');
        $currentSinger->setFirstName($newSinger->getFirstName());
        $currentSinger->setLastName($newSinger->getLastName());
        $errors = $validator->validate($currentSinger);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        $cache->invalidateTags(['singersCache']);
        $em->persist($currentSinger);
        $em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
