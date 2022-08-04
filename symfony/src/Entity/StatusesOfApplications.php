<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * StatusesOfApplications
 *
 * @ORM\Table(name="statuses_of_applications", indexes={})
 * @ORM\Entity
 */
class StatusesOfApplications
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="statuses_of_applications_id_seq", allocationSize=1, initialValue=1)
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=255, nullable=false)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255, nullable=false)
     */
    private $description;

    /**
     * @var string|null
     *
     * @ORM\Column(name="class_list", type="string", length=255, nullable=true)
     */
    private $classList;

    /**
     * @var string|null
     *
     * @ORM\Column(name="class_view", type="string", length=255, nullable=true)
     */
    private $classView;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getClassList(): ?string
    {
        return $this->classList;
    }

    public function setClassList(?string $classList): self
    {
        $this->classList = $classList;

        return $this;
    }

    public function getClassView(): ?string
    {
        return $this->classView;
    }

    public function setClassView(?string $classView): self
    {
        $this->classView = $classView;

        return $this;
    }
}
