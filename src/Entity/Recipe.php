<?php

namespace App\Entity;

use App\Repository\RecipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecipeRepository::class)]
class Recipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $text = null;

    #[ORM\ManyToOne(inversedBy: 'recipes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'recipes')]
    private Collection $categories;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ingredients = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $teaserImage = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'recipe', cascade: ['remove'], orphanRemoval: true)]
    private Collection $comments;

    #[ORM\Column(options: ['default' => 4])]
    private int $baseServings = 4;

    /**
     * @var Collection<int, RecipeSection>
     */
    #[ORM\OneToMany(targetEntity: RecipeSection::class, mappedBy: 'recipe', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $sections;

    /** Owner-controlled publication flag. */
    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    /** Admin lock. Overrules $active and cannot be lifted by the owner. */
    #[ORM\Column(options: ['default' => false])]
    private bool $blockedByAdmin = false;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->sections = new ArrayCollection();
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

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    public function getIngredients(): ?string
    {
        return $this->ingredients;
    }

    public function setIngredients(?string $ingredients): static
    {
        $this->ingredients = $ingredients;

        return $this;
    }

    public function getTeaserImage(): ?string
    {
        return $this->teaserImage;
    }

    public function setTeaserImage(?string $teaserImage): static
    {
        $this->teaserImage = $teaserImage;

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
            $comment->setRecipe($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getRecipe() === $this) {
                $comment->setRecipe(null);
            }
        }

        return $this;
    }

    public function getCommentCount(): int
    {
        return $this->comments->count();
    }

    public function getBaseServings(): int
    {
        return $this->baseServings;
    }

    public function setBaseServings(int $baseServings): static
    {
        $this->baseServings = $baseServings;

        return $this;
    }

    /**
     * @return Collection<int, RecipeSection>
     */
    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(RecipeSection $section): static
    {
        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->setRecipe($this);
        }

        return $this;
    }

    public function removeSection(RecipeSection $section): static
    {
        if ($this->sections->removeElement($section)) {
            if ($section->getRecipe() === $this) {
                $section->setRecipe(null);
            }
        }

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function isBlockedByAdmin(): bool
    {
        return $this->blockedByAdmin;
    }

    public function setBlockedByAdmin(bool $blockedByAdmin): static
    {
        $this->blockedByAdmin = $blockedByAdmin;

        return $this;
    }

    /**
     * A recipe reaches the public only when the owner published it, no admin
     * locked it and the owner's account itself is still active.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->active
            && !$this->blockedByAdmin
            && $this->owner?->isActive() === true;
    }
}
