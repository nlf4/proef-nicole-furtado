<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;


class SecurityController extends AbstractController
{

    private $email;
    private $password;
    private $result;
    private $signed_agreement;
    private $user_email;
    private $token;
    private $show_modal = false;
    private $api_url = "https://apidev.questi.com/2.0";
    private $grant_type = 'password';
    private $scope = 'sollicitatie-scope';
    private $client_id = 'q-sollicitatie-nifu';
    private $client_secret_pre = '5Wlu8Fq3wSBxIPa4vB9AOGPCyQ8QwVw0w5MjFzTXj8pdeDWziG';
    private $eul_content;

    /**
     * @Route("/", name="home", methods={"GET"})
     *
     */
    public function home()
    {
        return $this->render('security/home.html.twig');
    }

    /**
     * @Route("/login", name="login")
     * @param Request $request
     * @return Response
     */
    public function form(Request $request, EntityManagerInterface $em, SessionInterface $session): Response
    {
        /* Build login form *////////////
        $defaultData = [];
        $form = $this->createFormBuilder($defaultData)
            ->add('email', EmailType::class)
            ->add('password', PasswordType::class)
            ->add('send', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);

        /* Handle submit *///////////////
        if ($form->isSubmitted()) {

            $data= $form->getData();
            $this->email = $data['email'];
            $this->password = $data['password'];

            /* Client authentication *///////////////////////////
            $client = HttpClient::create();

            $response = $client->request('POST', $this->api_url.'/token/?', [
                'body' => [
                    'emailaddr' => $this->email,
                    'passwrd' => $this->password,
                    'grant_type' => $this->grant_type,
                    'scope' => $this->scope,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Client-Id' => $this->client_id,
                    'Client-Secret' => $this->calculateChecksum(),
                ]
            ]);

            $this->result = json_decode($response->getContent());
            /* Create new User */
            $user = new User();
            $user->setAccessToken($this->result->access_token);
//            var_dump($this->result->access_token);
//            var_dump($user);
            $this->token = $this->result->access_token;


            /*  User authentication  *//////////////////////////
            $client2 = HttpClient::create([
                'auth_bearer' => $this->result->access_token,
            ]);
            $response2 = $client2->request('GET', $this->api_url.'/user', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Client-Id' => $this->client_id,
                    'Client-Secret' => $this->calculateChecksum(),
                ]
            ]);

            $userData = json_decode($response2->getContent());
            var_dump($userData->result->signed_agreement);

            /* Save to User object *///////////
            $user->setName($userData->result->user_name)
                ->setFirstname($userData->result->user_firstname)
                ->setLanguage($userData->result->user_language)
                ->setEmail($userData->result->user_email)
                ->setSignedAgreement($userData->result->signed_agreement)
                ->setName($userData->result->user_name);

            /* Persist to database *///////////
            $em->persist($user);
            $em->flush();
            $session->set('current_user_id', $user->getId());

            return $this->redirectToRoute('profile');
            }

        return $this->render('security/new.html.twig', [
            'articleForm' => $form->createView(),
        ]);

    }

    /**
     * @Route("/profile", name="profile")
     * @param Request $request
     * @param UserRepository $userRepository
     * @param SessionInterface $session
     * @return Response
     */
    public function profile(Request $request, UserRepository $userRepository, SessionInterface $session)
    {
        /* Get user */
        $id = $session->get('current_user_id');
        $user = $userRepository->findOneBy(['id' => $id]);
//        dd($user);
        /* Get user agreement content if not signed *//////////////////
        if($user->getSignedAgreement() == 0) {

            $client = HttpClient::create([
                'auth_bearer' => $user->getAccessToken()
            ]);
            $response = $client->request('GET', $this->api_url.'/user/eul', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Client-Id' => $this->client_id,
                    'Client-Secret' => $this->calculateChecksum(),
                ]
            ]);

            $eulData = json_decode($response->getContent());
            $eul_status = $eulData->status;
            $session->set('eul_content', $eulData->result->eul_content);
//            dd($eulData);

            if ($eul_status === "success") {
                $this->show_modal = true;
            }
        }

        /* Build eul_sign form *////////////
        $defaultData = [];
        $eulForm = $this->createFormBuilder($defaultData)
            ->add('send', SubmitType::class, ['label' => 'Akkoord'])
            ->getForm();
        $eulForm->handleRequest($request);

        /* render template */
        $this->renderModal($request);
        return $this->render('profile.html.twig', [
            'eul_form' => $eulForm->createView(),
            'user' => $user,
            'modal' => $this->show_modal,
//            'agreement_text' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit',
            'agreement_text' => $session->get('eul_content'),
        ]);
    }

    /**
     * @Route("/eul_submit", name="eul_submit", methods={"POST, PUT"})
     */
    public function eulSubmit(Request $request, EntityManagerInterface $em, UserRepository $userRepository, SessionInterface $session)
    {
        /* Handle submit *///////////////
            /* Get user */
            $id = $session->get('current_user_id');
            $user = $userRepository->findOneBy(['id' => $id]);

           /* Update user data *//////////
            $client = HttpClient::create([
                'auth_bearer' => $user->getAccessToken()
            ]);
           $response = $client->request('PUT', $this->api_url.'/user/eul', [
                'body' => [
                    'signed_eul' => true,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Client-Id' => $this->client_id,
                    'Client-Secret' => $this->calculateChecksum(),
                ]
           ]);
            $data = json_decode($response->getContent());
//            dd($data->status);

            /* if eul submit is successful, re-load profile *///////////////////
            if ($data->status === "success") {
//                return $this->redirectToRoute('profile');
                return $this->render('profile.html.twig', [
                    'user' => $user,
                    'modal' => false,
                ]);
            }
    }


    public function calculateChecksum()
    {
        $date = date('Ymd');
        $client_secret = sha1(sha1($this->client_id . '_' . $this->client_secret_pre) . '_' . $date);
        return $client_secret;
    }

    public function renderModal(Request $request)
    {
        /* Build eul_sign form *////////////
        $defaultData = [];
        $eulForm = $this->createFormBuilder($defaultData)
            ->add('send', SubmitType::class, ['label' => 'Akkoord'])
            ->getForm();

        $eulForm->handleRequest($request);

        return $this->render('modal.html.twig', [
            'eul_form' => $eulForm->createView(),
            'modal' => $this->show_modal,
            'agreement_text' => $this->eul_content,
//            'user' => $user,
        ]);

    }
}