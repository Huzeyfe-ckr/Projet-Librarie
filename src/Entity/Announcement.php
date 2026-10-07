<?php

namespace App\Entity;

use App\Enum\AnnouncementStatus;
use App\Enum\AnnouncementType;
use App\Repository\AnnouncementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnnouncementRepository::class)]
class Announcement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(enumType: AnnouncementType::class)]
    private ?AnnouncementType $type = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(enumType: AnnouncementStatus::class)]
    private ?AnnouncementStatus $status = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $inactivatedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Exchange>
     */
    #[ORM\OneToMany(targetEntity: Exchange::class, mappedBy: 'announcement')]
    private Collection $exchanges;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'announcement')]
    private Collection $comments;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = AnnouncementStatus::Available;
        $this->exchanges = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getType(): ?AnnouncementType
    {
        return $this->type;
    }

    public function setType(AnnouncementType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getStatus(): ?AnnouncementStatus
    {
        return $this->status;
    }

    public function setStatus(AnnouncementStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getInactivatedAt(): ?\DateTimeImmutable
    {
        return $this->inactivatedAt;
    }

    public function setInactivatedAt(?\DateTimeImmutable $inactivatedAt): static
    {
        $this->inactivatedAt = $inactivatedAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, Exchange>
     */
    public function getExchanges(): Collection
    {
        return $this->exchanges;
    }

    public function addExchange(Exchange $exchange): static
    {
        if (!$this->exchanges->contains($exchange)) {
            $this->exchanges->add($exchange);
            $exchange->setAnnouncement($this);
        }

        return $this;
    }

    public function removeExchange(Exchange $exchange): static
    {
        if ($this->exchanges->removeElement($exchange)) {
            // set the owning side to null (unless already changed)
            if ($exchange->getAnnouncement() === $this) {
                $exchange->setAnnouncement(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setAnnouncement($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getAnnouncement() === $this) {
                $comment->setAnnouncement(null);
            }
        }

        return $this;
    }
}
