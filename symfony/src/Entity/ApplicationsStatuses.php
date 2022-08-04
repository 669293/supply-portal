<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ApplicationsStatuses
 *
 * @ORM\Table(name="applications_statuses", indexes={@ORM\Index(name="IDX_B450C22EA45BDDC1", columns={"application"}), @ORM\Index(name="IDX_B450C22E7B00651C", columns={"status"})})
 * @ORM\Entity
 */
class ApplicationsStatuses
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="applications_statuses_id_seq", allocationSize=1, initialValue=1)
     */
    private $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="datetime", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $datetime;

    /**
     * @var \Applications
     *
     * @ORM\ManyToOne(targetEntity="Applications")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="application", referencedColumnName="id")
     * })
     */
    private $application;

    /**
     * @var \StatusesOfApplications
     *
     * @ORM\ManyToOne(targetEntity="StatusesOfApplications")
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

    public function getApplication(): ?Applications
    {
        return $this->application;
    }

    public function setApplication(?Applications $application): self
    {
        $this->application = $application;

        return $this;
    }

    public function getStatus(): ?StatusesOfApplications
    {
        return $this->status;
    }

    public function setStatus(?StatusesOfApplications $status): self
    {
        $this->status = $status;

        return $this;
    }


}
