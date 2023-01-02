<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Stock
 *
 * @ORM\Table(name="stock_applications_materials")
 * @ORM\Entity(repositoryClass="App\Repository\StockApplicationsMaterialsRepository")
 */
class StockApplicationsMaterials
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="stock_applications_materials_id_seq", allocationSize=1, initialValue=1)
     */
    private $id;

    /**
     * @var \Stock
     *
     * @ORM\ManyToOne(targetEntity="Stock")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="stock", referencedColumnName="id")
     * })
     */
    private $stock;

    /**
     * @var \Materials
     *
     * @ORM\ManyToOne(targetEntity="Materials")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="material", referencedColumnName="id")
     * })
     */
    private $material;

    /**
     * @var string
     *
     * @ORM\Column(name="amount", type="decimal", precision=10, scale=0, nullable=false, options={"default"="0.0"})
     */
    private $amount = '0.0';

    public function __construct(
        $material = null, //Materials::class
        $stock = null, //Stock::class
        $amount = null
        )
    {
        if ($material !== null) {$this->material = $material;}
        if ($stock !== null) {$this->stock = $stock;}
        if ($amount !== null) {$this->amount = $amount;}
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getStock(): ?Stock
    {
        return $this->stock;
    }

    public function setStock(?Stock $stock): self
    {
        $this->stock = $stock;

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
}