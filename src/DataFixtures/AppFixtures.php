<?php

namespace App\DataFixtures;

use App\Entity\Record;
use App\Entity\Singer;
use App\Entity\Song;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail("user@recordapi.com");
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, "password"));
        $manager->persist($user);

        $userAdmin = new User();
        $userAdmin->setEmail("admin@recordapi.com");
        $userAdmin->setRoles(["ROLE_ADMIN"]);
        $userAdmin->setPassword($this->userPasswordHasher->hashPassword($userAdmin, "password"));
        $manager->persist($userAdmin);


        $listSinger = [];
        for ($i = 0; $i < 10; $i++) {
            $singer = new Singer;
            $singer->setFirstName('Prénom ' . $i);
            $singer->setLastName('Nom ' . $i);
            $manager->persist($singer);
            $listSinger[] = $singer;
        }

        $listRecord = [];
        for ($i = 0; $i < 20; $i++) {
            $record = new Record;
            $record->setName('Disque ' . $i);
            $record->setPrice($i);
            $record->setSinger($listSinger[array_rand($listSinger)]);
            $manager->persist($record);
            $listRecord[] = $record;
        }

        for ($i = 0; $i < 30; $i++) {
            $song = new Song;
            $song->setTitle('Titre ' . $i);
            $song->addAlbum($listRecord[array_rand($listRecord)]);
            $song->setLengthInSeconds(random_int(1, 600));
            $manager->persist($song);
        }
        $manager->flush();
    }
}
