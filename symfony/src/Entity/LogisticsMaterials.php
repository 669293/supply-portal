<?php

namespace App\Entity;

use App\Repository\LogisticsMaterialsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=LogisticsMaterialsRepository::class)
 */
class LogisticsMaterials
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="amount", type="decimal", precision=10, scale=0, nullable=false, options={"default"="0.0"})
     */
    private $amount = '0.0';

    /**
     * @var \Logistics
     *
     * @ORM\ManyToOne(targetEntity="Logistics")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="logistic", referencedColumnName="id")
     * })
     */
    private $logistic;

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

    public function getLogistics(): ?Logistics
    {
        return $this->logistic;
    }

    public function setLogistics(?Logistics $logistic): self
    {
        $this->logistic = $logistic;

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
