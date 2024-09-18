<?php

namespace App\Controller;

use App\Entity\Record;
use App\Repository\RecordRepository;
use App\Repository\SingerRepository;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class RecordController extends AbstractController
{
    #[Route('/api/records', name: 'record', methods: ['GET'])]
    public function getAllRecords(RecordRepository $recordRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 5);
        $idCache = "getAllRecords-" . $page . "-" . $limit;
        $context = SerializationContext::create()->setGroups(['getRecords']);
        $jsonRecordList = $cache->get($idCache, function (ItemInterface $item) use ($recordRepository, $page, $limit, $serializer, $context) {
            $item->tag("recordsCache");
            $item->expiresAfter(120);
            $recordList = $recordRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($recordList, 'json', $context);
        });
        return new JsonResponse($jsonRecordList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/record/{id}', name: 'recordDetail', methods: ['GET'])]
    public function getRecordDetail(Record $record, SerializerInterface $serializer): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['getRecords', 'getSongOfAlbum']);
        $jsonRecord = $serializer->serialize($record, 'json', $context);
        return new JsonResponse($jsonRecord, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[Route('/api/record/{id}', name: 'deleteRecord', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour supprimer un disque")]
    public function deleteRecord(Record $record, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(["recordsCache"]);
        $em->remove($record);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/records', name: 'createRecord', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour créer un disque")]
    public function createRecord(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, SingerRepository $singerRepository, ValidatorInterface $validator, SongRepository $songRepository)
    {
        $record = $serializer->deserialize($request->getContent(), Record::class, 'json');

        $errors = $validator->validate($record);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();

        $idSinger = $content['idSinger'] ?? -1;

        $record->setSinger($singerRepository->find($idSinger));

        $em->persist($record);
        $em->flush();
        $context = SerializationContext::create()->setGroups(["getRecords"]);
        $jsonRecord = $serializer->serialize($record, 'json', $context);

        $location = $urlGenerator->generate('recordDetail', ['id' => $record->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonRecord, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[Route('/api/record/{id}', name: "updateRecord", methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour mettre à jour une chanson")]
    public function updateRecord(Request $request, SerializerInterface $serializer, Record $currentRecord, EntityManagerInterface $em, SingerRepository $singerRepository, TagAwareCacheInterface $cache, ValidatorInterface $validator)
    {
        // On deserialize et récupère le contenu 
        $newRecord = $serializer->deserialize($request->getContent(), Record::class, 'json');
        // On attribut le contenu que l'on vient de récupérer
        $currentRecord->setName($newRecord->getName());
        $currentRecord->setPrice($newRecord->getPrice());
        $errors = $validator->validate($currentRecord);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        $content = $request->toArray();
        $idSinger = $content['idSinger'] ?? -1;

        $currentRecord->setSinger($singerRepository->find($idSinger));
        $em->persist($currentRecord);
        $em->flush();

        $cache->invalidateTags(["recordsCache"]);
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
