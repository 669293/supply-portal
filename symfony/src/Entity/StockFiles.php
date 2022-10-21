<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * StockFiles
 *
 * @ORM\Table(name="stock_files")
 * @ORM\Entity(repositoryClass="App\Repository\StockFilesRepository")
 */
class StockFiles
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="stock_files_id_seq", allocationSize=1, initialValue=1)
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="path", type="string", length=255, nullable=false)
     */
    private $path;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="datetime", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $datetime;

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
     * @var \Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="""user""", referencedColumnName="id")
     * })
     */
    private $user;

    public function __construct()
    {
        $this->datetime = new \DateTime();
    }

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

    public function getDatetime(): ?\DateTimeInterface
    {
        return $this->datetime;
    }

    public function setDatetime(\DateTimeInterface $datetime): self
    {
        $this->datetime = $datetime;

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

    public function getUser(): ?Users
    {
        return $this->user;
    }

    public function setUser(?Users $user): self
    {
        $this->user = $user;

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