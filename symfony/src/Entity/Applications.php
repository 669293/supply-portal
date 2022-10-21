<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Applications
 *
 * @ORM\Table(name="applications", indexes={@ORM\Index(name="IDX_F7C966F0BDAFD8C8", columns={"author"})})
 * @ORM\Entity(repositoryClass="App\Repository\ApplicationsRepository")
 */
class Applications
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="applications_id_seq", allocationSize=1, initialValue=1)
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
     * @ORM\Column(name="comment", type="text", nullable=true)
     */
    private $comment;

    /**
     * @var string
     *
     * @ORM\Column(name="number", type="string", length=255, nullable=true)
     */
    private $number;

    /**
     * @var \Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="author", referencedColumnName="id")
     * })
     */
    private $author;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_create", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $date_create;

    /**
     * @var \Date
     *
     * @ORM\Column(name="date_close", type="date", nullable=true)
     */
    private $date_close;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_bills_loaded", type="boolean", nullable=false)
     */
    private $isBillsLoaded = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_year", type="boolean", nullable=false)
     */
    private $isYear = false;

    /**
     * @var \Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="responsible", referencedColumnName="id")
     * })
     */
    private $responsible;

    public function __construct()
    {
        $this->date_create = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateCreate(): ?\DateTimeInterface
    {
        return $this->date_create;
    }

    public function setDateCreate(\DateTimeInterface $date_create): self
    {
        $this->date_create = $date_create;

        return $this;
    }

    public function getDateClose(): ?\DateTimeInterface
    {
        return $this->date_close;
    }

    public function setDateClose(\DateTimeInterface $date_close): self
    {
        $this->date_close = $date_close;

        return $this;
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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber($number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getAuthor(): ?Users
    {
        return $this->author;
    }

    public function setAuthor(?Users $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getIsBillsLoaded(): ?bool
    {
        return $this->isBillsLoaded;
    }

    public function setIsBillsLoaded(bool $isBillsLoaded): self
    {
        $this->isBillsLoaded = $isBillsLoaded;

        return $this;
    }

    public function getIsYear(): ?bool
    {
        return $this->isYear;
    }

    public function setIsYear(bool $isYear): self
    {
        $this->isYear = $isYear;

        return $this;
    }

    public function getResponsible(): ?Users
    {
        return $this->responsible;
    }

    public function setResponsible(?Users $responsible): self
    {
        $this->responsible = $responsible;

        return $this;
    }
}