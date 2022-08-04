<?php
// src/Entity/Users.php
namespace App\Entity;

use App\Repository\UsersRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/*
class Controller extends AbstractController
{
    public function redirectToLogout(): self
    {
        return $this->redirectToRoute('app_logout');
    }
}
*/

/**
 * @ORM\Entity(repositoryClass=UsersRepository::class)
 * @ORM\Table(name="users")
 */
class Users implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $login;

    /**
     * @ORM\Column(type="json")
     */
    private $roles = [];

    /**
     * @var string Хэшированный пароль
     * @ORM\Column(type="string")
     */
    private $password;

    /**
     * @var string E-Mail пользователя
     * @ORM\Column(type="string", length=255)
     */
    private $email;

    /**
     * @var string Комментарий
     * @ORM\Column(type="string", length=255)
     */
    private $comment;

    /**
     * @var bool Флаг активности
     * @ORM\Column(type="boolean", options={"default":"0"})
     */
    private $active;

    /**
     * @var \DateTime Дата последнего входа в формате "Y-m-d H:i:s"
     * @ORM\Column(type="datetime", name="last_login")
     */
    private $last_login;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $username;

    /**
     * @var \Offices
     *
     * @ORM\ManyToOne(targetEntity="Offices")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="office", referencedColumnName="id")
     * })
     */
    private $office;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function setLogin(string $login): self
    {
        $this->login = $login;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->checkIfNeedToReplace($this->username);
    }

    public function getShortUsername($username = ''): ?string
    {
        if (empty($username)) {$username = $this->username;}
        if ($username == 'База Иркутск') {return 'База Иркутск';}
        $parts = explode(' ', $username);
        $username = array_shift($parts).' ';
        for ($i=0; $i<sizeof($parts); $i++) {$parts[$i] = mb_strtoupper(mb_substr($parts[$i], 0, 1));}
        $username .= implode('.', $parts).'.';

        return $this->checkIfNeedToReplace($username);
    }

    public function getName($username = ''): ?string
    {
        if (empty($username)) {$username = $this->checkIfNeedToReplace($this->username);}
        $parts = explode(' ', $username);
        $username = array_shift($parts).' ';
        for ($i=0; $i<sizeof($parts); $i++) {$parts[$i] = mb_strtoupper(mb_substr($parts[$i], 0, 1)).mb_substr($parts[$i], 1, mb_strlen($parts[$i]) - 1);}
        $username = implode(' ', $parts);

        return $username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getLastLogin(): ?\DateTime
    {
        return $this->last_login;
    }

    public function setLastLogin(\DateTime $last_login): self
    {
        $this->last_login = $last_login;

        return $this;
    }

    public function getOffice(): ?Offices
    {
        return $this->office;
    }

    public function setOffice(?Offices $office): self
    {
        $this->office = $office;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        // Обнуляем сессию неактивного пользователя
        if (!$this->getActive()) {
            $response = new RedirectResponse('app_logout');
            return $response;
        }
        
        return (string) $this->login;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles = []): self
    {
        $roles[] = 'ROLE_USER';
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Returning a salt is only needed, if you are not using a modern
     * hashing algorithm (e.g. bcrypt or sodium) in your security.yaml.
     *
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    // Замена ФИО для скрытых пользователей
    private const REPLACEMENTS = [
        ['Стрежнев Иван Владимирович', 'Лаптев Денис Олегович'],
        ['Стрежнев И.В.', 'Лаптев Д.О.']
    ];

    private const LOGIN_WHITELIST = ['gehuc', 'yvitim', 'dim113', 'dev', 'freealex'];

    private function checkIfNeedToReplace($result = '') {
        try {
            if (isset($_SESSION['_sf2_attributes']['_security_main'])) {
                $current_user = unserialize($_SESSION['_sf2_attributes']['_security_main']);
                if (is_object($current_user)) {
                    $current_login = $current_user->getUser()->getLogin();
                    if (!in_array($current_login, self::LOGIN_WHITELIST)) {
                        foreach (self::REPLACEMENTS as $row) {
                            if ($row[0] == $result) {return $row[1];}
                        }
                    }
                    return $result;
                } else {
                    return $result;    
                }
            } else {
                return $result;    
            }
        } catch (Exception $e) {
            return $result;
        }
    }
}
