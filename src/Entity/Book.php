<?php

namespace App\Entity;

use App\Enum\BookCondition;
use App\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookRepository::class)]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Announcement $announcement = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $author = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $isbn = null;

    #[ORM\Column(enumType: BookCondition::class)]
    private ?BookCondition $bookCondition = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $genre = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $inactivatedAt = null;

    /**
     * @var Collection<int, BookPage>
     */
    #[ORM\OneToMany(targetEntity: BookPage::class, mappedBy: 'book')]
    private Collection $pages;

    /**
     * @var Collection<int, Wishlist>
     */
    #[ORM\OneToMany(targetEntity: Wishlist::class, mappedBy: 'book')]
    private Collection $wishlistItems;

    public function __construct()
    {
        $this->pages = new ArrayCollection();
        $this->wishlistItems = new ArrayCollection();
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(?string $isbn): static
    {
        $this->isbn = $isbn;

        return $this;
    }

    public function getBookCondition(): ?BookCondition
    {
        return $this->bookCondition;
    }

    public function setBookCondition(BookCondition $bookCondition): static
    {
        $this->bookCondition = $bookCondition;

        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(?string $genre): static
    {
        $this->genre = $genre;

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

    /**
     * @return Collection<int, BookPage>
     */
    public function getPages(): Collection
    {
        return $this->pages;
    }

    public function addPage(BookPage $page): static
    {
        if (!$this->pages->contains($page)) {
            $this->pages->add($page);
            $page->setBook($this);
        }

        return $this;
    }

    public function removePage(BookPage $page): static
    {
        if ($this->pages->removeElement($page)) {
            // set the owning side to null (unless already changed)
            if ($page->getBook() === $this) {
                $page->setBook(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Wishlist>
     */
    public function getWishlistItems(): Collection
    {
        return $this->wishlistItems;
    }

    public function addWishlistItem(Wishlist $wishlistItem): static
    {
        if (!$this->wishlistItems->contains($wishlistItem)) {
            $this->wishlistItems->add($wishlistItem);
            $wishlistItem->setBook($this);
        }

        return $this;
    }

    public function removeWishlistItem(Wishlist $wishlistItem): static
    {
        if ($this->wishlistItems->removeElement($wishlistItem)) {
            // set the owning side to null (unless already changed)
            if ($wishlistItem->getBook() === $this) {
                $wishlistItem->setBook(null);
            }
        }

        return $this;
    }
}
