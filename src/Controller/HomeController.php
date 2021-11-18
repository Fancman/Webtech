<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
     * @Route("/odborky", name="odborky")
     */
    public function odborky(): Response
    {
		$em = $this->getDoctrine()->getManager();
        $sql = "SELECT * FROM program";
        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute();
        $zaznamy = $stmt->fetchAll();

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
