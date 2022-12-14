<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * StockMaterials
 *
 * @ORM\Table(name="stock_materials")
 * @ORM\Entity(repositoryClass="App\Repository\StockMaterialsRepository")
 */
class StockMaterials
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="stock_materials_id_seq", allocationSize=1, initialValue=1)
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=1000, nullable=false)
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="price", type="decimal", precision=10, scale=0, nullable=false, options={"default"="0.0"})
     */
    private $price = '0.0';

    /**
     * @var \Units
     *
     * @ORM\ManyToOne(targetEntity="Units")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="unit", referencedColumnName="id")
     * })
     */
    private $unit;

    public function __construct(
        $title = null,
        $price = null,
        $unit = null, //Units::class
        )
    {
        if ($title !== null) {$this->title = $title;}
        if ($price !== null) {$this->price = $price;}
        if ($unit !== null) {$this->unit = $unit;}
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

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;

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
}