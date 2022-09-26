<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Bills
 *
 * @ORM\Table(name="bills")
 * @ORM\Entity
 */
class Bills
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="bills_id_seq", allocationSize=1, initialValue=1)
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="path", type="string", length=255, nullable=false)
     */
    private $path;

    /**
     * @var string
     *
     * @ORM\Column(name="sum", type="decimal", precision=10, scale=0, nullable=false, options={"default"="0.0"})
     */
    private $sum = '0.0';

    /**
     * @var string
     *
     * @ORM\Column(name="num", type="string", length=50, nullable=false)
     */
    private $num;

    /**
     * @var string
     *
     * @ORM\Column(name="inn", type="string", length=12, nullable=false)
     */
    private $inn;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_close", type="date", nullable=false)
     */
    private $dateClose;

    /**
     * @var \Users
     *
     * @ORM\ManyToOne(targetEntity="Users")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="""user""", referencedColumnName="id")
     * })
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(name="currency", type="string", length=4, nullable=false)
     */
    private $currency;

    /**
     * @var string
     *
     * @ORM\Column(name="note", type="string", length=255, nullable=true)
     */
    private $note;

    /**
     * @var string|null
     *
     * @ORM\Column(name="comment", type="text", nullable=true)
     */
    private $comment;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_printed", type="boolean", nullable=false)
     */
    private $isPrinted = false;

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

    public function getSum(): ?string
    {
        return $this->sum;
    }

    public function setSum(string $sum): self
    {
        $this->sum = $sum;

        return $this;
    }

    public function getNum(): ?string
    {
        return $this->num;
    }

    public function setNum(string $num): self
    {
        $this->num = $num;

        return $this;
    }

    public function getInn(): ?string
    {
        return $this->inn;
    }

    public function setInn(string $inn): self
    {
        $this->inn = $inn;

        return $this;
    }

    public function getDateClose(): ?\DateTimeInterface
    {
        return $this->dateClose;
    }

    public function setDateClose(\DateTimeInterface $dateClose): self
    {
        $this->dateClose = $dateClose;

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

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(string $note): self
    {
        if (empty($note)) {
            $this->note = null;
        } else {
            $this->note = $note;
        }

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        if (empty($comment)) {
            $this->comment = null;
        } else {
            $this->comment = $comment;
        }

        return $this;
    }

    public function getIsPrinted(): ?bool
    {
        return $this->isPrinted;
    }

    public function setIsPrinted(bool $isPrinted): self
    {
        $this->isPrinted = $isPrinted;

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

    //Функция определения имени файла
    public function getFilename(): string
    {
        return basename($this->path);
    }
}
