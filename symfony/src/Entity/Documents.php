<?php

namespace App\Entity;

use App\Repository\DocumentsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Bills;
use App\Entity\Users;

/**
 * Documents
 *
 * @ORM\Table(name="documents")
 * @ORM\Entity
 */
class Documents
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="files_id_seq", allocationSize=1, initialValue=1)
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
     * @var \Bills
     *
     * @ORM\ManyToOne(targetEntity="Bills")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="bill", referencedColumnName="id")
     * })
     */
    private $bill;

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

    public function getBill(): ?Bills
    {
        return $this->bill;
    }

    public function setBill(?Bills $bill): self
    {
        $this->bill = $bill;

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
