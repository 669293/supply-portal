<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ResponsibleLog
 *
 * @ORM\Table(name="responsible_log")
 * @ORM\Entity(repositoryClass="App\Repository\ResponsibleLogRepository")
 */
class ResponsibleLog
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="responsible_log_id_seq", allocationSize=1, initialValue=1)
     */
    private $id;

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
     * @var \Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="responsible", referencedColumnName="id")
     * })
     */
    private $responsible;

    /**
     * @var \Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="supervisor", referencedColumnName="id")
     * })
     */
    private $supervisor;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="datetime", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $datetime;

    public function __construct()
    {
        $this->datetime = new \DateTime();
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

    public function getResponsible(): ?Users
    {
        return $this->responsible;
    }

    public function setResponsible(?Users $responsible): self
    {
        $this->responsible = $responsible;

        return $this;
    }

    public function getSupervisor(): ?Users
    {
        return $this->supervisor;
    }

    public function setSupervisor(?Users $supervisor): self
    {
        $this->supervisor = $supervisor;

        return $this;
    }

    public function getDatetime(): ?\DateTimeInterface
    {
        return $this->datetime;
    }

    public function setDatetime(?\DateTimeInterface $datetime): self
    {
        $this->datetime = $datetime;

        return $this;
    }
}
