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


class SecurityController extends AbstractController
{

    private $email;
    private $password;
    private $result;
    private $signed_agreement;
    private $user_email;
    private $token;
    private $show_modal;
    private $api_url = "https://apidev.questi.com/2.0";
    private $grant_type = 'password';
    private $scope = 'sollicitatie-scope';
    private $client_id = 'q-sollicitatie-nifu';
    private $client_secret_pre = '5Wlu8Fq3wSBxIPa4vB9AOGPCyQ8QwVw0w5MjFzTXj8pdeDWziG';

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
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */

    public function form(Request $request, EntityManagerInterface $em): Response
    {
        /* Build login form *////////////
        $defaultData = ['email' => 'jorn200fr@questi.com'];
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
            var_dump($user);

            return $this->redirectToRoute('profile');

            }

        return $this->render('security/new.html.twig', [
            'articleForm' => $form->createView(),
        ]);

    }

    /**
     * @Route("/profile", name="profile")
     */
    public function profile(UserRepository $userRepository)
    {
        /* Get user */
        $user = $userRepository->findOneBy(['email' => $this->email]);

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
//            var_dump($eulData->result["eul_content"]);
            $html = "This is a sample user agreement text.";
            if (isset($html) && $html !== "") {
                $this->show_modal = true;
            }
        }

        /* render template */
        return $this->render('profile.html.twig', [
            'user' => $user,
            'show-modal' => true,
        ]);

    }

    /**
     * @Route("/eul_submit", name="eul_submit", methods={"POST, PUT"})
     */
    public function eulSubmit(Request $request, EntityManagerInterface $em, UserRepository $userRepository)
    {
        /* Build form *////////////
        $defaultData = [];
        $form = $this->createFormBuilder($defaultData)
            ->add('send', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);

        /* Handle submit *///////////////
        if ($form->isSubmitted()) {

            /* Get user */
            $user = $userRepository->findOneBy(['email' => $this->email]);

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
        }

        return $this->render('profile.html.twig', [
            'eul_form' => $form->createView(),
        ]);

        /* render template */
//        return $this->render('profile.html.twig', [
//            'user' => $user,
//            'show-modal' => false,
//        ]);
    }

    public function calculateChecksum()
    {
        $date = date('Ymd');
        $client_secret = sha1(sha1($this->client_id . '_' . $this->client_secret_pre) . '_' . $date);
        return $client_secret;
    }
}