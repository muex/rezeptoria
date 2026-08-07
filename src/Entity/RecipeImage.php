<?php

namespace App\Entity;

use App\Repository\RecipeImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One picture of the recipe's gallery. The teaser image is not one of these:
 * it stays on the recipe itself, because it has a job the gallery has not —
 * being the single image that represents the recipe in listings and previews.
 */
#[ORM\Entity(repositoryClass: RecipeImageRepository::class)]
class RecipeImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Recipe $recipe = null;

    /**
     * File name under public/uploads/images. Written by RecipeImageUpdater once
     * the upload is on disk, so it carries no constraint of its own — a row
     * that never received a file is dropped instead of being reported.
     */
    #[ORM\Column(length: 255)]
    private ?string $filename = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Die Bildunterschrift darf höchstens {{ limit }} Zeichen lang sein.')]
    private ?string $caption = null;

    /** Position within the gallery, taken from the order the form submitted the images in. */
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipe(): ?Recipe
    {
        return $this->recipe;
    }

    public function setRecipe(?Recipe $recipe): static
    {
        $this->recipe = $recipe;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): static
    {
        $this->caption = $caption;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
