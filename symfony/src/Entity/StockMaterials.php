<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Stock
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
     * @ORM\SequenceGenerator(sequenceName="stock_id_seq", allocationSize=1, initialValue=1)
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
     * @var string
     *
     * @ORM\Column(name="count", type="decimal", precision=10, scale=0, nullable=false, options={"default"="0.0"})
     */
    private $count = '0.0';

    /**
     * @var string
     *
     * @ORM\Column(name="sum", type="decimal", precision=10, scale=0, nullable=false, options={"default"="0.0"})
     */
    private $sum = '0.0';

    /**
     * @var string
     *
     * @ORM\Column(name="tax", type="decimal", precision=10, scale=0, nullable=false, options={"default"="0.0"})
     */
    private $tax = '0.0';

    /**
     * @var string
     *
     * @ORM\Column(name="total", type="decimal", precision=10, scale=0, nullable=false, options={"default"="0.0"})
     */
    private $total = '0.0';

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
     * @var \Stock
     *
     * @ORM\ManyToOne(targetEntity="Stock")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="stock", referencedColumnName="id")
     * })
     */
    private $stock;

    public function __construct(
        $title = null,
        $price = null,
        $count = null,
        $sum = null,
        $tax = null,
        $total = null,
        $unit = null, //Units::class
        $stock = null //Stock::class
        )
    {
        if ($title !== null) {$this->title = $title;}
        if ($price !== null) {$this->price = $price;}
        if ($count !== null) {$this->count = $count;}
        if ($sum !== null) {$this->sum = $sum;}
        if ($tax !== null) {$this->tax = $tax;}
        if ($total !== null) {$this->total = $total;}
        if ($unit !== null) {$this->unit = $unit;}
        if ($stock !== null) {$this->stock = $stock;}
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

    public function getCount(): ?string
    {
        return $this->count;
    }

    public function setCount(string $count): self
    {
        $this->count = $count;

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

    public function getTax(): ?string
    {
        return $this->tax;
    }

    public function setTax(string $tax): self
    {
        $this->tax = $tax;

        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(string $total): self
    {
        $this->total = $total;

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

    public function getStock(): ?Stock
    {
        return $this->stock;
    }

    public function setStock(?Stock $stock): self
    {
        $this->stock = $stock;

        return $this;
    }
}