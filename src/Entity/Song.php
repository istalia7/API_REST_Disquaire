<?php

namespace App\Entity;

use App\Repository\SongRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation\Since;

/**
 * @Hateoas\Relation(
 *      "self",
 *      href= @Hateoas\Route(
 *          "songDetail",
 *          parameters = { "id" = "expr(object.getId())" },
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getSongs")
 * )
 * 
 * @Hateoas\Relation(
 *      "delete",
 *      href = @Hateoas\Route(
 *          "deleteSong",
 *          parameters = { "id" = "expr(object.getId())" },
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getSongs", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
 * )
 * 
 * @Hateoas\Relation(
 *      "update",
 *      href = @Hateoas\Route(
 *          "updateSong",
 *          parameters = { "id" = "expr(object.getId())" },
 *      ),
 *      exclusion = @Hateoas\Exclusion(groups="getSongs", excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
 * )
 * 
 */
#[ORM\Entity(repositoryClass: SongRepository::class)]
class Song
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getSongs", "getSongOfAlbum"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getSongs", "getSongOfAlbum"])]
    #[Assert\NotBlank(message: "Le titre de la chanson ne peut pas être vide")]
    #[Assert\Type('string')]
    private ?string $title = null;

    #[ORM\Column(nullable: true)]
    #[Groups(["getSongs"])]
    #[Since("2.0")]
    private ?int $lengthInSeconds = null;

    /**
     * @var Collection<int, Record>
     */
    #[ORM\ManyToMany(targetEntity: Record::class, inversedBy: 'songs')]
    #[Groups(["getAlbumOfSong"])]
    private Collection $album;

    public function __construct()
    {
        $this->album = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return Collection<int, Record>
     */
    public function getAlbum(): Collection
    {
        return $this->album;
    }

    public function addAlbum(Record $album): static
    {
        if (!$this->album->contains($album)) {
            $this->album->add($album);
        }

        return $this;
    }

    public function removeAlbum(Record $album): static
    {
        $this->album->removeElement($album);

        return $this;
    }


    /**
     * Set the value of album
     *
     * @param Collection $album
     *
     * @return self
     */
    public function setAlbum(Collection $album): self
    {
        $this->album = $album;

        return $this;
    }

    public function getLengthInSeconds(): ?int
    {
        return $this->lengthInSeconds;
    }

    public function setLengthInSeconds(int $lengthInSeconds): static
    {
        $this->lengthInSeconds = $lengthInSeconds;

        return $this;
    }
}
