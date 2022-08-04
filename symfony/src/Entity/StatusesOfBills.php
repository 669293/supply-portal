<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * StatusesOfBills
 *
 * @ORM\Table(name="statuses_of_bills")
 * @ORM\Entity
 */
class StatusesOfBills
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="statuses_of_bills_id_seq", allocationSize=1, initialValue=1)
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=255, nullable=false)
     */
    private $title;

    /**
     * @var string|null
     *
     * @ORM\Column(name="class_btn", type="string", length=255, nullable=true)
     */
    private $classbtn;

    /**
     * @var string|null
     *
     * @ORM\Column(name="class_text", type="string", length=255, nullable=true)
     */
    private $classtext;

    /**
     * @var string|null
     *
     * @ORM\Column(name="class_bg", type="string", length=255, nullable=true)
     */
    private $classbg;

    /**
     * @var string|null
     *
     * @ORM\Column(name="class_view", type="string", length=255, nullable=true)
     */
    private $classView;

    /**
     * @var string|null
     *
     * @ORM\Column(name="icon", type="string", length=255, nullable=true)
     */
    private $icon;

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

    public function getClassBtn(): ?string
    {
        return $this->classbtn;
    }

    public function setClassBtn(?string $classbtn): self
    {
        $this->classbtn = $classbtn;

        return $this;
    }

    public function getClassText(): ?string
    {
        return $this->classtext;
    }

    public function setClassText(?string $classtext): self
    {
        $this->classtext = $classtext;

        return $this;
    }

    public function getClassBg(): ?string
    {
        return $this->classbg;
    }

    public function setClassBg(?string $classbg): self
    {
        $this->classbg = $classbg;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

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
