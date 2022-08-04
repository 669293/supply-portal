<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BillsMaterials
 *
 * @ORM\Table(name="bills_materials", indexes={@ORM\Index(name="IDX_F4302EFC7A2119E3", columns={"bill"}), @ORM\Index(name="IDX_F4302EFC7CBE7595", columns={"material"})})
 * @ORM\Entity
 */
class BillsMaterials
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="bills_materials_id_seq", allocationSize=1, initialValue=1)
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="amount", type="decimal", precision=10, scale=0, nullable=false, options={"default"="0.0"})
     */
    private $amount = '0.0';

    /**
     * @var string
     *
     * @ORM\Column(name="recieved", type="decimal", precision=10, scale=0, nullable=false, options={"default"="0.0"})
     */
    private $recieved = '0.0';

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
     * @var \Materials
     *
     * @ORM\ManyToOne(targetEntity="Materials")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="material", referencedColumnName="id")
     * })
     */
    private $material;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getRecieved(): ?string
    {
        return $this->recieved;
    }

    public function setRecieved(string $recieved): self
    {
        $this->recieved = $recieved;

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

    public function getMaterial(): ?Materials
    {
        return $this->material;
    }

    public function setMaterial(?Materials $material): self
    {
        $this->material = $material;

        return $this;
    }
}
