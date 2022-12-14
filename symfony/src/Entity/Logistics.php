<?php

namespace App\Entity;

use App\Repository\LogisticsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=LogisticsRepository::class)
 */
class Logistics
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var \Date
     *
     * @ORM\Column(name="""date""", type="date", nullable=false)
     */
    private $date;

    /**
     * @var int
     *
     * @ORM\Column(name="""parent""", type="integer", nullable=true)
     */
    private $parent;

    /**
     * @var integer
     *
     * @ORM\Column(name="""type""", type="integer", nullable=false)
     */
    private $type;

    /**
     * @var string|null
     *
     * @ORM\Column(name="way", type="string", length=255, nullable=true)
     */
    private $way;

    /**
     * @var string|null
     *
     * @ORM\Column(name="track", type="string", length=255, nullable=true)
     */
    private $track;

    /**
     * @var \Offices
     *
     * @ORM\ManyToOne(targetEntity="Offices")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="office", referencedColumnName="id")
     * })
     */
    private $office;

    /**
     * @var \Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="""user""", referencedColumnName="id")
     * })
     */
    private $user;

    /**
     * @var bool
     *
     * @ORM\Column(name="done", type="boolean", nullable=false)
     */
    private $done = false;

    /**
     * @var \Bills
     *
     * @ORM\ManyToOne(targetEntity="Bills")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="bill", referencedColumnName="id")
     * })
     */
    private $bill;


    /**
     * @var string
     *
     * @ORM\Column(name="docinfo", type="string", length=255, nullable=false)
     */
    private $docinfo;

    /**
     * @var string
     *
     * @ORM\Column(name="sum", type="decimal", precision=10, scale=0)
     */
    private $sum;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getParent(): ?int
    {
        return $this->parent;
    }

    public function setParent(?int $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getWay(): ?string
    {
        return $this->way;
    }

    public function setWay(string $way): self
    {
        $this->way = $way;

        return $this;
    }

    public function getTrack(): ?string
    {
        return $this->track;
    }

    public function setTrack(string $track): self
    {
        $this->track = $track;

        return $this;
    }

    public function getOffice(): ?Offices
    {
        return $this->office;
    }

    public function setOffice(?Offices $office): self
    {
        $this->office = $office;

        return $this;
    }

    public function getUser(): ?Users
    {
        return $this->user;
    }

    public function setUser(?Users $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getDone(): ?bool
    {
        return $this->done;
    }

    public function setDone(bool $done): self
    {
        $this->done = $done;

        return $this;
    }

    public function getBill(): ?Bills
    {
        return $this->bill;
    }

    public function setBill(?Bills $bill): self
    {
        $this->bill = $bill;

        return $this;
    }

    public function getDocInfo(): ?string
    {
        return $this->docinfo;
    }

    public function setDocInfo(string $docinfo): self
    {
        $this->docinfo = $docinfo;

        return $this;
    }

    public function getSum(): ?string
    {
        return $this->sum;
    }

    public function setSum(string $sum): self
    {
        $this->sum = $sum;

        return $this;
    }
}
