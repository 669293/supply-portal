<?php
// src/Controller/UsersManageController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\Criteria;
use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Repository\OfficesRepository;

class UsersManageController extends AbstractController
{
    private $security;
    private $hasher;
    private $manager;
    protected $roles_list;

    public function __construct(Security $security, UserPasswordHasherInterface $hasher, EntityManagerInterface $manager)
    {
        $this->security = $security;
        $this->hasher = $hasher;
        $this->manager = $manager;
        $this->roles_list = [
            'ROLE_ADMIN' => ['Администратор', 'bg-danger'],
            'ROLE_SUPERVISOR' => ['Назначение исполнителей', 'bg-success'],
            'ROLE_CREATOR' => ['Создание заявок', 'bg-secondary'],
            'ROLE_EXECUTOR' => ['Исполнение заявок', 'bg-primary'],
            'ROLE_LOGISTICS' => ['Добавление логистической информации', 'bg-warning text-dark'],
            'ROLE_BUH' => ['Бухгалтерия', 'bg-warning text-dark'],
            'ROLE_STOCK' => ['Склад', 'bg-warning text-dark'],
            'ROLE_WATCHER' => ['Наблюдатель', 'bg-success'],
            'ROLE_THIEF' => ['Не может видеть суммы и счета', 'bg-danger'],
        ];
    }

    /**
     * Смена пароля текущего пользователя
     * @Route("/change-password", methods={"POST"})
     * @IsGranted("ROLE_USER")
     */
    public function changePassword(Request $request): JsonResponse
    {
        $result = [];

        $submittedToken = $request->request->get('token');

        if ($this->isCsrfTokenValid('change-password', $submittedToken)) {
            $currentPassword = $request->request->get('currentPassword');
            $newPassword = $request->request->get('newPassword');
            
            #Проверяем текущий пароль
            $user = $this->security->getUser();
            if (!$this->hasher->isPasswordValid($user, $currentPassword)) {
                $result[] = 0;
                $result[] = 'Введен неверный пароль';
                return new JsonResponse($result);
            }

            $user->setPassword($this->hasher->hashPassword($user, $newPassword));

            $this->manager->persist($user);
            $this->manager->flush();

            $result[] = 1;
            $result[] = '';
            return new JsonResponse($result);
        } else {
            $result[] = 0;
            $result[] = 'Недействительный токен CSRF.';
            return new JsonResponse($result);
        }
    }

    /**
     * Список пользователей
     * @Route("/users", methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function showUsersList(LoginLinkHandlerInterface $loginLinkHandler, UsersRepository $usersRepository, Request $request): Response
    {
        $users = $usersRepository->findBy(
            array(),
            array('active' => 'DESC', 'username' => 'ASC')
        );

        $users_list = [];

        foreach ($users as $user) {
            $user_ = new \stdClass();
            $user_->login = $user->getLogin();
            $user_->username = $user->getUserName();
            $user_->email = $user->getEmail();
            $user_->comment = $user->getComment(); if ($user_->comment == null) $user_->comment = 'Нет';
            $user_->roles = [];
            $user_->lastlogin = $user->getLastLogin(); $user_->lastlogin != null ? $user_->lastlogin = $user_->lastlogin->format('d.m.Y H:i:s') : $user_->lastlogin = 'Нет';
            $user_->active = $user->getActive();
            $user_->office = $user->getOffice();

            $loginLinkDetails = $loginLinkHandler->createLoginLink($user);
            $user_->login_link = $loginLinkDetails->getUrl();

            $roles = $user->getRoles();
            foreach ($roles as $role) {
                if (in_array($role, array_keys($this->roles_list))) {
                    $role_ = new \stdClass();
                    $role_->title = $this->roles_list[$role][0];
                    $role_->class = $this->roles_list[$role][1];
                    array_push($user_->roles, $role_);
                    unset($role_);
                }
            }

            array_push($users_list, $user_);
            unset($roles, $user_);
        }

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/users';
        $breadcrumbs[0]->title = 'Управление пользователями';

        $params = [
            'title' => 'Управление пользователями',
            'breadcrumbs' => $breadcrumbs,
            'users' => $users_list
        ];

        //Проверяем есть ли уведомление
        if ($request->request->get('msg') !== null && $request->request->get('bg-color') !== null && $request->request->get('text-color') !== null) {
            ob_start();

            echo '<script type="text/javascript">'."\n";
            echo '    $(document).ready(function() {'."\n";
            echo '        addToast(\''.$request->request->get('msg').'\', \''.$request->request->get('bg-color').'\', \''.$request->request->get('text-color').'\');'."\n";
            echo '        showToasts();'."\n";
            echo '    });'."\n";
            echo '</script>'."\n";

            $scripts = ob_get_contents();
            ob_end_clean();

            $params['scripts'] = $scripts;
        }

        return $this->render('users/index.html.twig', $params);
    }

    /**
     * Форма добавления пользователя
     * @Route("/users/add", methods={"GET"}))
     * @IsGranted("ROLE_ADMIN")
     */
    public function showAddUserForm(OfficesRepository $officesRepository): Response
    {
        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/users';
        $breadcrumbs[0]->title = 'Управление пользователями';
        $breadcrumbs[1] = new \stdClass();
        $breadcrumbs[1]->href = '/users/add';
        $breadcrumbs[1]->title = 'Добавление пользователя';

        return $this->render('users/add.html.twig', [
            'title' => 'Добавление пользователя',
            'breadcrumbs' => $breadcrumbs,
            'offices' => $officesRepository->findAll(),
            'allroles' => $this->roles_list
        ]);
    }

    /**
     * Добавление нового пользователя (принимает данные из формы)
     * @Route("/users/add", methods={"POST"}))
     * @IsGranted("ROLE_ADMIN")
     */
    public function addNewUser(Request $request, UsersRepository $usersRepository, OfficesRepository $officesRepository): JsonResponse
    {
        $result = [];

        $submittedToken = $request->request->get('token');

        if ($this->isCsrfTokenValid('add-user', $submittedToken)) {
            $user = new Users;
            $user->setUsername($request->request->get('userName'));
            $user->setLogin($request->request->get('userLogin'));
            $user->setPassword($this->hasher->hashPassword($user, $request->request->get('userPassword')));
            $user->setEmail($request->request->get('userEmail'));
            $user->setComment($request->request->get('userComment'));
            
            $roles = []; //['ROLE_USER']; 
            if ($request->request->get('userRole')) {$roles = array_merge($roles, $request->request->get('userRole'));}
            $user->setRoles($roles);
            
            $user->setActive(true);

            if ($request->request->get('userOffice') != null && $request->request->get('userOffice') != -1) {
                //Получаем офис
                $objOffice = $officesRepository->findBy( array('id' => $request->request->get('userOffice')) );
                if (is_array($objOffice)) {$objOffice = array_shift($objOffice);}
                $user->setOffice($objOffice);
            }

            //Проверяем уникальность логина
            if (sizeof($usersRepository->findBy(array('login' => $user->getLogin()))) != 0) {
                $result[] = 0;
                $result[] = 'Пользователь с таким логином уже существует';
                $result[] = 'userLogin';

                return new JsonResponse($result);
            }

            try {
                $this->manager->persist($user);
                $this->manager->flush();

                $result[] = 1;
                $result[] = $user->getLogin();
            } catch (Exception $e) {
                $result[] = 0;
                $result[] = 'Ошибка в данных: '.$e;
            }

            return new JsonResponse($result);
        } else {
            $result[] = 0;
            $result[] = 'Недействительный токен CSRF.';
            return new JsonResponse($result);
        }
    }

    /**
     * Форма редактирования пользователя
     * @Route("/users/edit", methods={"GET"}))
     * @IsGranted("ROLE_ADMIN")
     */
    public function showEditUserForm(Request $request, UsersRepository $usersRepository, OfficesRepository $officesRepository): Response
    {
        $login = $request->query->get('login');
        if ($login === null || empty($login)) {
            return new RedirectResponse('/users');
        }

        $user = $usersRepository->findBy(array('login' => $login));
        if (sizeof($user) == 0) {
            return new RedirectResponse('/users');
        }

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/users';
        $breadcrumbs[0]->title = 'Управление пользователями';
        $breadcrumbs[1] = new \stdClass();
        $breadcrumbs[1]->href = '/users/edit';
        $breadcrumbs[1]->title = 'Редактирование пользователя';

        return $this->render('users/edit.html.twig', [
            'title' => 'Редактирование пользователя',
            'breadcrumbs' => $breadcrumbs,
            'user' => $user[0],
            'offices' => $officesRepository->findAll(),
            'allroles' => $this->roles_list
        ]);
    }

    /**
     * Сохранение данных пользователя (принимает данные из формы)
     * @Route("/users/edit", methods={"POST"}))
     * @IsGranted("ROLE_ADMIN")
     */
    public function saveUser(Request $request, UsersRepository $usersRepository, OfficesRepository $officesRepository): JsonResponse
    {
        $result = [];

        $submittedToken = $request->request->get('token');

        if ($this->isCsrfTokenValid('edit-user', $submittedToken)) {
            $user = $usersRepository->find((int)$request->request->get('userId'));

            if ($user === null) {
                $result[] = 0;
                $result[] = 'Пользователя с таким идентификатором не существует';

                return new JsonResponse($result);
            }

            $user->setUsername($request->request->get('userName'));
            $user->setLogin($request->request->get('userLogin'));
            if (!empty($request->request->get('userPassword'))) {
                $user->setPassword($this->hasher->hashPassword($user, $request->request->get('userPassword')));
            }
            $user->setEmail($request->request->get('userEmail'));
            $user->setComment($request->request->get('userComment'));

            $roles = []; //['ROLE_USER']; 
            if ($request->request->get('userRole')) {$roles = array_merge($roles, $request->request->get('userRole'));}
            $roles = array_unique($roles);
            $user->setRoles($roles);

            if ($request->request->get('userActive') !== null) {
                $user->setActive(true);
            } else {
                $user->setActive(false);
            }

            if ($request->request->get('userOffice') != null && $request->request->get('userOffice') != -1) {
                //Получаем офис
                $objOffice = $officesRepository->findBy( array('id' => $request->request->get('userOffice')) );
                if (is_array($objOffice)) {$objOffice = array_shift($objOffice);}
                $user->setOffice($objOffice);
            } else {
                $user->setOffice(null);
            }

            //Проверяем уникальность логина
            $uCheck = $usersRepository->findBy(array('login' => $user->getLogin()));
            if (sizeof($uCheck) > 0 && $uCheck[0]->getId() != (int)$request->request->get('userId')) {
                $result[] = 0;
                $result[] = 'Пользователь с таким логином уже существует';
                $result[] = 'userLogin';

                return new JsonResponse($result);
            }

            try {
                $this->manager->persist($user);
                $this->manager->flush();

                $result[] = 1;
                $result[] = '';
            } catch (Exception $e) {
                $result[] = 0;
                $result[] = 'Ошибка в данных: '.$e;
            }

            return new JsonResponse($result);
        } else {
            $result[] = 0;
            $result[] = 'Недействительный токен CSRF.';
            return new JsonResponse($result);
        }
    }
}