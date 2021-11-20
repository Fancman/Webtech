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
     * @Route("/api/activities", name="activities")
     */
    public function activities(Connection $connection, Request $request): Response
    {
		$request_content = strval($request->getContent());
    	$params = json_decode($request_content, true);
		$age_category_id = $request->query->get('age_category_id');

		$response = new Response();

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');

		if($params)
			$age_category_id = is_null($age_category_id) ? (array_key_exists("age_category_id", $params) ? $params["age_category_id"] : null) : $age_category_id;

		if($age_category_id){
			$resultSet = $connection->executeQuery('SELECT na.id, na.name, na.img_url, na.level, na.activity_type, nac.id as age_category_id, nac.name as age_category FROM new_activity na LEFT JOIN new_age_category nac ON na.age_category_id=nac.id WHERE na.age_category_id = ?', [
				$age_category_id
			]);

			$zaznamy = $resultSet->fetchAllAssociative();

			$response->setContent(json_encode($zaznamy));

        	return $response;
		}

		$sql = "SELECT na.id, na.name, na.img_url, na.level, na.activity_type, nac.id as age_category_id, nac.name as age_category FROM new_activity na LEFT JOIN new_age_category nac ON na.age_category_id=nac.id";
        $zaznamy = $connection->fetchAllAssociative($sql);

        $response->setContent(json_encode($zaznamy));

        return $response;
    }

	/**
     * @Route("/api/login", methods={"POST"}, name="login")
     */
    public function login(Connection $connection, Request $request): Response
    {
		// the query string is '?foo=bar'

		$username = $request->query->get('username');
		$password = $request->query->get('password');

		$request_content = strval($request->getContent());
    	$params = json_decode($request_content, true);

		$username = is_null($username) ? (array_key_exists("username", $params) ? $params["username"] : null) : $username;
		$password = is_null($password) ? (array_key_exists("password", $params) ? $params["password"] : null) : $password;

		$response = new Response();

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');


		if(isset($username)
		&& isset($password)){
			try {

				$resultSet = $connection->executeQuery('SELECT users.* FROM new_users WHERE username = ? AND password = ?', [
					$username,
					$password
				]);

				$user = $resultSet->fetchAssociative();

			} catch (\Throwable $th) {
				$response->setStatusCode(Response::HTTP_BAD_REQUEST);

				$response->setContent(json_encode([
					'msg' => 'DB select didnt work.',
					'error' => $th->getMessage()
				]));

				return $response;
			}

			if(!isset($user) || is_null($user)){
				$response->setStatusCode(Response::HTTP_NOT_FOUND);
				$response->setContent(json_encode([
					'msg' => 'Login was not succesful'
				]));

				return $response;
			}

			$response->setContent(json_encode([
				'user_id' => $user['user_id'],
				'age' => $user['age'],
				'datum_registracie' => $user['datum_registracie'],
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

	/**
     * @Route("/api/register", methods={"POST"}, name="register")
     */
    public function register(Connection $connection, Request $request): Response
    {
		// the query string is '?foo=bar'

		$username = $request->query->get('username');
		$password = $request->query->get('password');
		$firstname = $request->query->get('password');
		$lastname = $request->query->get('password');
		$age = $request->query->get('age');

		$request_content = strval($request->getContent());
    	$params = json_decode($request_content, true);

		$username = is_null($username) ? (array_key_exists("username", $params) ? $params["username"] : null) : $username;
		$password = is_null($password) ? (array_key_exists("password", $params) ? $params["password"] : null) : $password;
		$firstname = is_null($firstname) ? (array_key_exists("firstname", $params) ? $params["firstname"] : null) : $firstname;
		$lastname = is_null($lastname) ? (array_key_exists("lastname", $params) ? $params["lastname"] : null) : $lastname;
		$age = is_null($age) ? (array_key_exists("age", $params) ? $params["age"] : null) : $age;

		$response = new Response();

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');


		if(isset($username)
		&& isset($password)
		&& isset($age)){
			try {

				$resultSet = $connection->executeQuery('SELECT * FROM `new_age_category` WHERE ? BETWEEN min_age AND max_age', [
					$age
				]);

				$age_obj = $resultSet->fetchAssociative();

				$count = $connection->executeStatement('INSERT INTO new_users (username, password, age, firstname, lastname) VALUES (?, ?, ?, ?, ?)', [
					$username,
					$password,
					intval($age_obj['id']),
					$firstname,
					$lastname
				]);

				$created_id = $connection->lastInsertId();

			} catch (\Throwable $th) {
				$response->setStatusCode(Response::HTTP_BAD_REQUEST);

				$response->setContent(json_encode([
					'msg' => 'Could not be inserted into DB.',
					'error' => $th->getMessage()
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
