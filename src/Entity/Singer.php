<?php

namespace App\Entity;

use App\Repository\SingerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Hateoas\Configuration\Annotation as Hateoas;

/**
 * @Hateoas\Relation(
 *      "self",
 *      href= @Hateoas\Route(
 *          "singerDetail",
 *          parameters = { "id" = "expr(object.getId())" },
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getSingers")
 * )
 * 
 * @Hateoas\Relation(
 *      "delete",
 *      href = @Hateoas\Route(
 *          "deleteSinger",
 *          parameters = { "id" = "expr(object.getId())" },
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getSingers", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
 * )
 * 
 * @Hateoas\Relation(
 *      "update",
 *      href = @Hateoas\Route(
 *          "updateSinger",
 *          parameters = { "id" = "expr(object.getId())" },
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getSingers", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
 * )
 * 
 */
#[ORM\Entity(repositoryClass: SingerRepository::class)]
class Singer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getRecords", "getSingers", "getAlbumOfSong"])]
    private ?int $id = null;

    #[ORM\Column(length: 75)]
    #[Groups(["getRecords", "getSingers", "getAlbumOfSong"])]
    #[Assert\NotBlank(message: "Le prénom du chanteur ne peut pas être vide")]
    #[Assert\Type('string')]
    private ?string $firstName = null;

    #[ORM\Column(length: 75)]
    #[Groups(["getRecords", "getSingers", "getAlbumOfSong"])]
    #[Assert\NotBlank(message: "Le nom du chanteur ne peut pas être vide")]
    #[Assert\Type('string')]
    private ?string $lastName = null;

    /**
     * @var Collection<int, Record>
     */
    #[ORM\OneToMany(targetEntity: Record::class, mappedBy: 'singer', cascade: ['remove'])]
    #[Groups(["getSingerRecord"])]
    private Collection $records;

    public function __construct()
    {
        $this->records = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * @return Collection<int, Record>
     */
    public function getRecords(): Collection
    {
        return $this->records;
    }

    public function addRecord(Record $record): static
    {
        if (!$this->records->contains($record)) {
            $this->records->add($record);
            $record->setSinger($this);
        }

        return $this;
    }

    public function removeRecord(Record $record): static
    {
        if ($this->records->removeElement($record)) {
            // set the owning side to null (unless already changed)
            if ($record->getSinger() === $this) {
                $record->setSinger(null);
            }
        }

        return $this;
    }
}
