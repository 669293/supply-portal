<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * StockStockMaterials
 *
 * @ORM\Table(name="stock_stock_materials")
 * @ORM\Entity(repositoryClass="App\Repository\StockStockMaterialsRepository")
 */
class StockStockMaterials
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="stock_stock_materials_id_seq", allocationSize=1, initialValue=1)
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
     * @var \StockMaterials
     *
     * @ORM\ManyToOne(targetEntity="StockMaterials")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="stock_material", referencedColumnName="id")
     * })
     */
    private $stockMaterial;

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

    public function __construct(
        $stock = null, //Stock::class
        $stockMaterial = null, //StockMaterials::class
        $count = null,
        $sum = null,
        $tax = null,
        $total = null
        )
    {
        if ($stock !== null) {$this->stock = $stock;}
        if ($stockMaterial !== null) {$this->stockMaterial = $stockMaterial;}
        if ($count !== null) {$this->count = $count;}
        if ($sum !== null) {$this->sum = $sum;}
        if ($tax !== null) {$this->tax = $tax;}
        if ($total !== null) {$this->total = $total;}

    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getStockMaterial(): ?StockMaterials
    {
        return $this->stockMaterial;
    }

    public function setStockMaterial(?StockMaterials $stockMaterial): self
    {
        $this->stockMaterial = $stockMaterial;

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
}