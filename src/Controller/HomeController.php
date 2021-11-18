<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\DBAL\Connection;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="app_home")
     */
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

	/**
     * @Route("/api/odborky", name="odborky")
     */
    public function odborky(Connection $connection): Response
    {
		$sql = "SELECT * FROM program";
        $zaznamy = $connection->fetchAllAssociative($sql);

		$response = new Response();

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');

        $response->setContent(json_encode($zaznamy));

        return $response;

        return $this->render('odborky.html.twig', [
			'zaznamy' => $zaznamy
		]);
    }

}
