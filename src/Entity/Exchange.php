<?php

namespace App\Entity;

use App\Enum\ExchangeStatus;
use App\Repository\ExchangeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExchangeRepository::class)]
class Exchange
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'exchanges')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Announcement $announcement = null;

    #[ORM\ManyToOne(inversedBy: 'exchanges')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $requester = null;

    #[ORM\Column(enumType: ExchangeStatus::class)]
    private ?ExchangeStatus $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $initiatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\UniqueConstraint(columns: ['announcement_id', 'requester_id'])]


    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'exchange')]
    private Collection $messages;

    public function __construct()
    {
        $this->initiatedAt = new \DateTimeImmutable();
        $this->status = ExchangeStatus::Pending;
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnnouncement(): ?Announcement
    {
        return $this->announcement;
    }

    public function setAnnouncement(?Announcement $announcement): static
    {
        $this->announcement = $announcement;

        return $this;
    }

    public function getRequester(): ?User
    {
        return $this->requester;
    }

    public function setRequester(?User $requester): static
    {
        $this->requester = $requester;

        return $this;
    }

    public function getStatus(): ?ExchangeStatus
    {
        return $this->status;
    }

    public function setStatus(ExchangeStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getInitiatedAt(): ?\DateTimeImmutable
    {
        return $this->initiatedAt;
    }

    public function setInitiatedAt(\DateTimeImmutable $initiatedAt): static
    {
        $this->initiatedAt = $initiatedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setExchange($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getExchange() === $this) {
                $message->setExchange(null);
            }
        }

        return $this;
    }
}
