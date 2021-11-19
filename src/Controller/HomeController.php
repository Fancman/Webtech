<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
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

	/**
     * @Route("/api/register", methods={"POST"}, name="register")
     */
    public function register(Connection $connection, Request $request): Response
    {
		// the query string is '?foo=bar'

		$username = $request->query->get('username');
		$password = $request->query->get('password');
		$age = $request->query->get('age');

		$request_content = strval($request->getContent());
    	$params = json_decode($request_content, true);

		$username = is_null($username) ? (array_key_exists("username", $params) ? $params["username"] : null) : $username;
		$password = is_null($password) ? (array_key_exists("password", $params) ? $params["password"] : null) : $password;
		$age = is_null($age) ? (array_key_exists("age", $params) ? $params["age"] : null) : $age;

		$response = new Response();

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');


		if(isset($username)
		&& isset($password)
		&& isset($age)){
			try {

				$count = $connection->executeStatement('INSERT INTO users (username, password, age) VALUES (?, ?, ?)', [
					$username,
					$password,
					$age
				]);

				$created_id = $connection->lastInsertId();

			} catch (\Throwable $th) {
				$response->setStatusCode(Response::HTTP_BAD_REQUEST);

				$response->setContent(json_encode([
					'msg' => 'Could not be inserted into DB.'
				]));

				return $response;
			}

			$response->setContent(json_encode([
				'id' => $created_id
			]));

			$response->setStatusCode(Response::HTTP_OK);

			return $response;
		}

		$response->setStatusCode(Response::HTTP_BAD_REQUEST);

        $response->setContent(json_encode([
			'msg' => 'Wrong request'
		]));

        return $response;
    }

}
