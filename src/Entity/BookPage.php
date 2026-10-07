<?php

namespace App\Entity;

use App\Repository\BookPageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookPageRepository::class)]
class BookPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'pages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Book $book = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $imgUrl = null;

    #[ORM\Column]
    private ?int $pageOrder = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $uploadedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $inactivatedAt = null;

    #[ORM\UniqueConstraint(columns: ['book_id', 'page_order'])]
    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(?Book $book): static
    {
        $this->book = $book;

        return $this;
    }

    public function getImgUrl(): ?string
    {
        return $this->imgUrl;
    }

    public function setImgUrl(string $imgUrl): static
    {
        $this->imgUrl = $imgUrl;

        return $this;
    }

    public function getPageOrder(): ?int
    {
        return $this->pageOrder;
    }

    public function setPageOrder(int $pageOrder): static
    {
        $this->pageOrder = $pageOrder;

        return $this;
    }

    public function getUploadedAt(): ?\DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeImmutable $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;

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
}
