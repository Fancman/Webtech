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
		$sql = "SELECT p.program_id,
		p.program_name,
		p.program_photo,
		p.program_info,
		p.program_pozn,
		vk.vekova_kat_name,
		s.stupen_name
		FROM `program` p
		LEFT JOIN program_kat pk ON p.program_kat_id=pk.program_kat_id
		LEFT JOIN vekova_kat vk ON p.vekova_kat_id=vk.vekova_kat_id
		LEFT JOIN stupen s ON p.stupen_id=s.stupen_id
		AND pk.program_kat_name = 'Odborky'";
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
