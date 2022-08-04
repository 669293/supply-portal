<?php

namespace App\Entity;

use App\Repository\PhotosRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PhotosRepository::class)
 */
class Photos
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
     * @ORM\Column(name="path", type="string", length=255, nullable=false)
     */
    private $path;

    /**
     * @var \Logistics
     *
     * @ORM\ManyToOne(targetEntity="Logistics")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="logistic", referencedColumnName="id")
     * })
     */
    private $logistic;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

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

    //Функция определения типа файла
    public function getFileType($filename = null): string
    {
        if ($filename === null) {$filename = basename($this->path);}
        $tmp = explode('.', $filename); $ext = end($tmp); unset($tmp);
        if (in_array($ext, ['doc', 'docx', 'xls', 'xlsx'])) {return 'office';}
        if (in_array($ext, ['pdf'])) {return 'pdf';}
        if (in_array($ext, ['txt'])) {return 'text';}
        if (in_array($ext, ['htm', 'html'])) {return 'html';}
        return 'image';
    }
}
