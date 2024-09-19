<?php

namespace App\Controller;

use App\Entity\Record;
use App\Entity\Song;
use App\Repository\RecordRepository;
use App\Repository\SongRepository;
use App\Service\VersioningService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;

class SongController extends AbstractController
{
    #[OA\Get(
        path: "/api/songs",
        summary: "Cette méthode permet de Récupérer toutes les chansons",
        parameters: [
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), description: "La page que l'on veut récupérer"),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer"), description: "Le nombre de chanteurs que l'on veut récupérer par page")
        ],
        responses: [
            new OA\Response(response: 200, description: "Retourne la liste des chanteurs", content: new OA\JsonContent(type: "array", items: new OA\Items(ref: new Model(type: Song::class, groups: ["getSongs"]))))
        ],
        tags: ["Songs"]
    )]
    #[Route('/api/songs', name: 'song', methods: ['GET'])]
    public function getAllSongs(SongRepository $songRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache, VersioningService $versioningService): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 5);
        $idCache = "getAllSongs-" . $page . "-" . $limit;
        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(['getSongs']);
        $context->setVersion($version);
        $jsonSongList = $cache->get($idCache, function (ItemInterface $item) use ($songRepository, $page, $limit, $serializer, $context) {
            $item->tag("songsCache");
            $item->expiresAfter(120);
            $songList = $songRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($songList, 'json', $context);
        });
        return new JsonResponse($jsonSongList, Response::HTTP_OK, [], true);
    }

    #[OA\Get(
        path: "/api/song/{id}",
        summary: "Cette méthode permet de récupérer les détails d'une chanson",
        parameters: [
            new OA\Parameter(name: "id", in: "path", schema: new OA\Schema(type: "integer"), description: "L'id de la chanson que l'on veut récupérer")
        ],
        responses: [
            new OA\Response(response: 200, description: "Retourne les détails d'une chanson", content: new OA\JsonContent(ref: new Model(type: Song::class, groups: ["getSongs"])))
        ],
        tags: ["Songs"]

    )]
    #[Route('/api/song/{id}', name: 'songDetail', methods: ['GET'])]
    public function getSongDetail(Song $song, SerializerInterface $serializer, VersioningService $versioningService): JsonResponse
    {
        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(['getSongs', 'getAlbumOfSong']);
        $context->setVersion($version);
        $jsonSong = $serializer->serialize($song, 'json', $context);
        return new JsonResponse($jsonSong, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[OA\Delete(
        path: "/api/song/{id}",
        summary: "Cette méthode permet de supprimer un chanteur",
        parameters: [
            new OA\Parameter(name: "id", in: "path", schema: new OA\Schema(type: "integer", description: "L'id de la chanson à supprimer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Chanteur supprimé")
        ],
        tags: ["Songs"]
    )]
    #[Route('/api/song/{id}', name: 'deleteSong', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour supprimer une chanson")]
    public function deleteSong(Song $song, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(["songsCache"]);
        $em->remove($song);
        $em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Post(
        path: "/api/songs",
        summary: "Cette méthode permet de créer une chanson",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['title', 'lengthInSeconds', 'idAlbums'],
                    properties: [
                        new OA\Property(property: 'title', type: 'string', example: 'TitreChanson'),
                        new OA\Property(property: 'lengthInSeconds', type: 'integer', example: 360),
                        new OA\Property(property: 'idAlbums', type: 'array', items: new OA\Items(type: 'integer'), example: '[1, 2, 4]')
                    ]
                )
            )
        ),
        tags: ["Songs"]
    )]
    #[Route('/api/songs', name: 'createSong', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour créer une chanson")]
    public function createSong(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator, RecordRepository $recordRepository, VersioningService $versioningService): JsonResponse
    {
        $song = $serializer->deserialize($request->getContent(), Song::class, 'json');

        // Fetching the api version
        $version = $versioningService->getVersion();

        $song->setAlbum(new ArrayCollection());

        $content = $request->toArray();
        $idAlbums = $content['idAlbums'] ?? [];

        if (version_compare($version, '2.0', '>=') && $song->getLengthInSeconds() === null) {
            return new JsonResponse(['error' => 'The length of the song is required for API version ' . $version]);
        }

        foreach ($idAlbums as $idAlbum) {
            $album = $recordRepository->find($idAlbum);
            if ($album) {
                $song->addAlbum($album);
            }
        }

        $song->addAlbum($recordRepository->find($idAlbum));

        $errors = $validator->validate($song);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($song);
        $em->flush();
        $context = SerializationContext::create()->setGroups(["getSongs"]);
        $context->setVersion($version);
        $jsonSong = $serializer->serialize($song, 'json', $context);
        $location = $urlGenerator->generate('songDetail', ['id' => $song->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonSong, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[OA\Put(
        path: "/api/song/{id}",
        summary: "Cette méthode permet de mettre à jour une chanson",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(
                    type: "object",
                    required: ["title"],
                    properties: [
                        new OA\Property(property: 'title', type: 'string', example: 'TitreChanson')
                    ]
                )
            )
        ),
        tags: ["Songs"]
    )]
    #[Route('api/song/{id}', name: 'updateSong', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour mettre une chanson à jour")]
    public function updateSong(Request $request, SerializerInterface $serializer, Song $currentSong, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache, VersioningService $versioningService): JsonResponse
    {
        $newSong = $serializer->deserialize($request->getContent(), Song::class, 'json');
        $currentSong->setTitle($newSong->getTitle());
        $errors = $validator->validate($currentSong);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        $cache->invalidateTags(['songsCache']);
        $em->persist($currentSong);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
