<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * StockTransport
 *
 * @ORM\Table(name="stock_transport")
 * @ORM\Entity(repositoryClass="App\Repository\StockTransportRepository")
 */
class StockTransport
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="stock_transport_id_seq", allocationSize=1, initialValue=1)
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
     * @ORM\Column(name="regnum", type="string", length=9, nullable=true)
     */
    private $regnum;

    /**
     * @var string
     *
     * @ORM\Column(name="driver", type="string", length=255, nullable=true)
     */
    private $driver;

    /**
     * @var string
     *
     * @ORM\Column(name="signature", type="string", length=255, nullable=true)
     */
    private $signature;

    /**
     * @var string
     *
     * @ORM\Column(name="style", type="string", length=255, nullable=true)
     */
    private $style;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getRegnum(): ?string
    {
        return $this->regnum;
    }

    public function setRegnum(?string $regnum): self
    {
        $this->regnum = $regnum;

        return $this;
    }

    public function getDriver(): ?string
    {
        return $this->driver;
    }

    public function setDriver(?string $driver): self
    {
        $this->driver = $driver;

        return $this;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function setSignature(?string $signature): self
    {
        $this->signature = $signature;

        return $this;
    }

    public function getStyle(): ?string
    {
        return $this->style;
    }

    public function setStyle(?string $style): self
    {
        $this->style = $style;

        return $this;
    }
}