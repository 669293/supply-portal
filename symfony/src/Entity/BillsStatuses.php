<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BillsStatuses
 *
 * @ORM\Table(name="bills_statuses", indexes={@ORM\Index(name="IDX_DCBFB6C77A2119E3", columns={"bill"}), @ORM\Index(name="IDX_DCBFB6C77B00651C", columns={"status"})})
 * @ORM\Entity
 */
class BillsStatuses
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="bills_statuses_id_seq", allocationSize=1, initialValue=1)
     */
    private $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="datetime", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $datetime;

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
     * @var \StatusesOfBills
     *
     * @ORM\ManyToOne(targetEntity="StatusesOfBills")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="status", referencedColumnName="id")
     * })
     */
    private $status;

    public function __construct()
    {
        $this->datetime = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDatetime(): ?\DateTimeInterface
    {
        return $this->datetime;
    }

    public function setDatetime(\DateTimeInterface $datetime): self
    {
        $this->datetime = $datetime;

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

    public function getStatus(): ?StatusesOfBills
    {
        return $this->status;
    }

    public function setStatus(?StatusesOfBills $status): self
    {
        $this->status = $status;

        return $this;
    }


}
