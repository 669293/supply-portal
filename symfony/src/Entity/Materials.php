<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Materials
 *
 * @ORM\Table(name="materials", indexes={@ORM\Index(name="IDX_9B1716B5DCBB0C53", columns={"unit"}), @ORM\Index(name="IDX_9B1716B5631FCF80", columns={"type_of_equipment"}), @ORM\Index(name="IDX_9B1716B597E625E8", columns={"responsible"}), @ORM\Index(name="IDX_9B1716B5A45BDDC1", columns={"application"})})
 * @ORM\Entity
 */
class Materials
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="materials_id_seq", allocationSize=1, initialValue=1)
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
     * @ORM\Column(name="amount", type="decimal", precision=10, scale=0, nullable=false)
     */
    private $amount = '0';

    /**
     * @var bool
     *
     * @ORM\Column(name="urgency", type="boolean", nullable=false)
     */
    private $urgency = false;

    /**
     * @var string
     *
     * @ORM\Column(name="num", type="decimal", precision=10, scale=0, nullable=false)
     */
    private $num = '0';

    /**
     * @var bool
     *
     * @ORM\Column(name="is_deleted", type="boolean", nullable=false)
     */
    private $isDeleted = false;

    /**
     * @var string|null
     *
     * @ORM\Column(name="comment", type="string", length=1000, nullable=true)
     */
    private $comment;

    /**
     * @var string|null
     *
     * @ORM\Column(name="note", type="text", nullable=true)
     */
    private $note;

    /**
     * @var \Units
     *
     * @ORM\ManyToOne(targetEntity="Units")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="unit", referencedColumnName="id")
     * })
     */
    private $unit;

    /**
     * @var \TypesOfEquipment
     *
     * @ORM\ManyToOne(targetEntity="TypesOfEquipment")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="type_of_equipment", referencedColumnName="id")
     * })
     */
    private $typeOfEquipment;

    /**
     * @var \Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="responsible", referencedColumnName="id")
     * })
     */
    private $responsible;

    /**
     * @var \Applications
     *
     * @ORM\ManyToOne(targetEntity="Applications")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="application", referencedColumnName="id")
     * })
     */
    private $application;

    /**
     * @var bool
     *
     * @ORM\Column(name="impossible", type="boolean", nullable=false)
     */
    private $impossible = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="cash", type="boolean", nullable=false)
     */
    private $cash = false;

    /**
     * @var bool
     *
     * @ORM\Column(name="requested", type="boolean", nullable=false)
     */
    private $requested = false;

    public function __construct(
        $title = null,
        $amount = null,
        $unit = null, //Units::class
        $urgency = null,
        $typeOfEquipment = null, //TypesOfEquipment::class
        $comment = null,
        $note = null,
        $application = null, //Applications::class
        $num = null,
        $isDeleted = null,
        $impossible = null,
        $cash = null,
        $requested = null
        )
    {
        if ($title !== null) {$this->title = $title;}
        if ($amount !== null) {$this->amount = $amount;}
        if ($unit !== null) {$this->unit = $unit;}
        if ($urgency !== null) {$this->urgency = $urgency;}
        if ($typeOfEquipment !== null) {$this->typeOfEquipment = $typeOfEquipment;}
        if ($note !== null) {$this->note = $note;}
        if ($comment !== null) {$this->comment = $comment;}
        if ($application !== null) {$this->application = $application;}
        if ($num !== null) {$this->num = $num;}
        if ($isDeleted !== null) {$this->isDeleted = $isDeleted;}
        if ($requested !== null) {$this->requested = $requested;}
    }

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

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getUrgency(): ?bool
    {
        return $this->urgency;
    }

    public function setUrgency(bool $urgency): self
    {
        $this->urgency = $urgency;

        return $this;
    }

    public function getNum(): ?string
    {
        return $this->num;
    }

    public function setNum(string $num): self
    {
        $this->num = $num;

        return $this;
    }

    public function getIsDeleted(): ?bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): self
    {
        $this->isDeleted = $isDeleted;

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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }

    public function getUnit(): ?Units
    {
        return $this->unit;
    }

    public function setUnit(?Units $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    public function getTypeOfEquipment(): ?TypesOfEquipment
    {
        return $this->typeOfEquipment;
    }

    public function setTypeOfEquipment(?TypesOfEquipment $typeOfEquipment): self
    {
        $this->typeOfEquipment = $typeOfEquipment;

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

    public function getApplication(): ?Applications
    {
        return $this->application;
    }

    public function setApplication(?Applications $application): self
    {
        $this->application = $application;

        return $this;
    }

    public function getImpossible(): ?bool
    {
        return $this->impossible;
    }

    public function setImpossible(bool $impossible): self
    {
        $this->impossible = $impossible;

        return $this;
    }

    public function getCash(): ?bool
    {
        return $this->cash;
    }

    public function setCash(bool $cash): self
    {
        $this->cash = $cash;

        return $this;
    }

    public function getRequested(): ?bool
    {
        return $this->requested;
    }

    public function setRequested(bool $requested): self
    {
        $this->requested = $requested;

        return $this;
    }
}
