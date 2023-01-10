<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * StockBalance
 *
 * @ORM\Table(name="stock_balance")
 * @ORM\Entity(repositoryClass="App\Repository\StockBalanceRepository")
 */
class StockBalance
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="stock_balance_id_seq", allocationSize=1, initialValue=1)
     */
    private $id;

    /**
     * @var \StockStockMaterials
     *
     * @ORM\ManyToOne(targetEntity="StockStockMaterials")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="stock_stock_material", referencedColumnName="id")
     * })
     */
    private $ssm;

    public function __construct(
        $ssm = null //StockStockMaterial::class
        )
    {
        if ($ssm !== null) {$this->ssm = $ssm;}

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStockStockMaterial(): ?StockStockMaterials
    {
        return $this->ssm;
    }

    public function setStockStockMaterial(?StockStockMaterials $ssm): self
    {
        $this->ssm = $ssm;

        return $this;
    }
}