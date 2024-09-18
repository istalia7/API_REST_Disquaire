<?php

namespace App\Entity;

use App\Repository\RecordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecordRepository::class)]
class Record
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getRecords", "getSingerRecord", "getAlbumOfSong"])]
    private ?int $id = null;

    #[ORM\Column(length: 75)]
    #[Groups(["getRecords", "getSingerRecord", "getAlbumOfSong"])]
    #[Assert\NotBlank(message: "Le nom du disque ne peut pas être vide")]
    #[Assert\Type('string')]
    private ?string $name = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Groups(["getRecords", "getSingerRecord", "getAlbumOfSong"])]
    #[Assert\NotBlank(message: "Le disque doit avoir un prix")]
    private ?string $price = null;

    #[ORM\ManyToOne(inversedBy: 'records')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(["getRecords", "getAlbumOfSong"])]
    // #[Assert\NotBlank(message: "Le disque doit appartenir à un chanteur")]
    private ?Singer $singer = null;

    /**
     * @var Collection<int, Song>
     */
    #[ORM\ManyToMany(targetEntity: Song::class, mappedBy: 'album', cascade: ['remove'])]
    #[Groups(["getSongOfAlbum"])]
    private Collection $songs;

    public function __construct()
    {
        $this->songs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getSinger(): ?Singer
    {
        return $this->singer;
    }

    public function setSinger(?Singer $singer): static
    {
        $this->singer = $singer;

        return $this;
    }

    /**
     * @return Collection<int, Song>
     */
    public function getSongs(): Collection
    {
        return $this->songs;
    }

    public function addSong(Song $song): static
    {
        if (!$this->songs->contains($song)) {
            $this->songs->add($song);
            $song->addAlbum($this);
        }

        return $this;
    }

    public function removeSong(Song $song): static
    {
        if ($this->songs->removeElement($song)) {
            $song->removeAlbum($this);
        }

        return $this;
    }
}
