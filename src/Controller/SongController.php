<?php

namespace App\Controller;

use App\Entity\Record;
use App\Entity\Song;
use App\Repository\RecordRepository;
use App\Repository\SongRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
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

class SongController extends AbstractController
{
    #[Route('/api/songs', name: 'song', methods: ['GET'])]
    public function getAllSongs(SongRepository $songRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 5);
        $idCache = "getAllSongs-" . $page . "-" . $limit;
        $context = SerializationContext::create()->setGroups(['getSongs']);
        $jsonSongList = $cache->get($idCache, function (ItemInterface $item) use ($songRepository, $page, $limit, $serializer, $context) {
            $item->tag("songsCache");
            $item->expiresAfter(120);
            $songList = $songRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($songList, 'json', $context);
        });
        return new JsonResponse($jsonSongList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/song/{id}', name: 'songDetail', methods: ['GET'])]
    public function getSongDetail(Song $song, SerializerInterface $serializer): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['getSongs', 'getAlbumOfSong']);
        $jsonSong = $serializer->serialize($song, 'json', $context);
        return new JsonResponse($jsonSong, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[Route('/api/song/{id}', name: 'deleteSong', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour supprimer une chanson")]
    public function deleteSong(Song $song, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(["songsCache"]);
        $em->remove($song);
        $em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/songs', name: 'createSong', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour créer une chanson")]
    public function createSong(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator, RecordRepository $recordRepository): JsonResponse
    {
        $song = $serializer->deserialize($request->getContent(), Song::class, 'json');

        // Retrieve the album ID from the request
        // $data = json_decode($request->getContent(), true);
        // $albumId = $data['album_id'] ?? null;

        // if ($albumId) {
        //     // Fetch the album (Record entity) by ID
        //     $album = $recordRepository->find($albumId);

        //     // If album is not found, return a 404 error
        //     if (!$album) {
        //         return new JsonResponse(['message' => 'Album not found'], JsonResponse::HTTP_NOT_FOUND);
        //     }

        //     // Link the album to the song
        //     $song->addAlbum($album);
        // }

        $errors = $validator->validate($song);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($song);
        $em->flush();
        $context = SerializationContext::create()->setGroups(["getSongs"]);
        $jsonSong = $serializer->serialize($song, 'json', $context);
        $location = $urlGenerator->generate('songDetail', ['id' => $song->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonSong, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[Route('api/song/{id}', name: 'updateSong', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour mettre une chanson à jour")]
    public function updateSong(Request $request, SerializerInterface $serializer, Song $currentSong, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
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
