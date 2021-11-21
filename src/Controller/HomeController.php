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
     * @Route("/api/age_categories", name="age_categories")
     */
    public function age_categories(Connection $connection, Request $request): Response
    {
		$response = new Response();

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');

		$resultSet = $connection->executeQuery('SELECT * from new_age_category');

		$zaznamy = $resultSet->fetchAllAssociative();

		$response->setContent(json_encode($zaznamy));

        return $response;

	}

	/**
     * @Route("/api/progress", name="progress")
     */
    public function progress(Connection $connection, Request $request): Response
    {
		$request_content = strval($request->getContent());
    	$params = json_decode($request_content, true);

		$user_id = $request->query->get('user_id');
		$task_id = $request->query->get('task_id');

		$response = new Response();

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');

		if($params){
			$user_id = is_null($user_id) ? (array_key_exists("user_id", $params) ? $params["user_id"] : null) : $user_id;
			$task_id = is_null($task_id) ? (array_key_exists("task_id", $params) ? $params["task_id"] : null) : $task_id;
		}

		if(isset($user_id)
		&& isset($task_id)){
			try {
				$count = $connection->executeStatement('INSERT INTO new_task_progress (user_id, task_id) VALUES (?, ?)', [
					$user_id,
					$task_id
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

	/**
     * @Route("/api/activities", name="activities")
     */
    public function activities(Connection $connection, Request $request): Response
    {
		$request_content = strval($request->getContent());
    	$params = json_decode($request_content, true);

		$age_category_id = $request->query->get('age_category_id');
		$activity_type = $request->query->get('activity_type');

		$response = new Response();

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');

		if($params)
			$age_category_id = is_null($age_category_id) ? (array_key_exists("age_category_id", $params) ? $params["age_category_id"] : null) : $age_category_id;

		if($params)
			$activity_type = is_null($activity_type) ? (array_key_exists("activity_type", $params) ? $params["activity_type"] : null) : $activity_type;

		if($age_category_id || $activity_type){
			if($age_category_id && $activity_type){
				$resultSet = $connection->executeQuery('SELECT na.id, na.name, na.img_url, na.level, na.activity_type, nac.id as age_category_id, nac.name as age_category
				FROM new_activity na LEFT JOIN new_age_category nac ON na.age_category_id=nac.id
				WHERE 1=1 AND na.age_category_id = ? AND na.activity_type = ?', [
					$age_category_id,
					$activity_type
				]);
			}else if($age_category_id){
				$resultSet = $connection->executeQuery('SELECT na.id, na.name, na.img_url, na.level, na.activity_type, nac.id as age_category_id, nac.name as age_category
				FROM new_activity na LEFT JOIN new_age_category nac ON na.age_category_id=nac.id
				WHERE 1=1 AND na.age_category_id = ?', [
					$age_category_id
				]);
			}else if($activity_type){
				$resultSet = $connection->executeQuery('SELECT na.id, na.name, na.img_url, na.level, na.activity_type, nac.id as age_category_id, nac.name as age_category
				FROM new_activity na LEFT JOIN new_age_category nac ON na.age_category_id=nac.id
				WHERE 1=1 AND na.activity_type = ?', [
					$activity_type
				]);
			}

			$zaznamy = $resultSet->fetchAllAssociative();

			foreach ($zaznamy as $idx => $z) {
				$resultSet = $connection->executeQuery('SELECT * FROM new_task WHERE activity_id = ?', [
					$z['id']
				]);

				$zaznamy[$idx]['tasks'] = $resultSet->fetchAllAssociative();
			}

			$response->setContent(json_encode($zaznamy));

        	return $response;
		}

		$sql = "SELECT na.id, na.name, na.img_url, na.level, na.activity_type, nac.id as age_category_id, nac.name as age_category FROM new_activity na LEFT JOIN new_age_category nac ON na.age_category_id=nac.id";
        $zaznamy = $connection->fetchAllAssociative($sql);

		foreach ($zaznamy as $idx => $z) {
			$resultSet = $connection->executeQuery('SELECT * FROM new_task WHERE activity_id = ?', [
				$z['id']
			]);

			$zaznamy[$idx]['tasks'] = $resultSet->fetchAllAssociative();
		}

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

				$resultSet = $connection->executeQuery('SELECT new_users.* FROM new_users WHERE username = ? AND password = ?', [
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

			if($user == false){
				$response->setStatusCode(Response::HTTP_NOT_FOUND);

				$response->setContent(json_encode([
					'msg' => 'Login was not succesful'
				]));

				return $response;
			}

			$response->setContent(json_encode([
				'id' => $user['id'],
				'age' => $user['age'],
				'age_category_id' => $user['age_category_id'],
				'firstname' => $user['firstname'],
				'lastname' => $user['lastname'],
				'username' => $user['username'],
				'registrated_at' => $user['registrated_at'],
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
		$firstname = $request->query->get('firstname');
		$lastname = $request->query->get('lastname');
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

				$count = $connection->executeStatement('INSERT INTO new_users (username, firstname, lastname, password, age_category_id, age) VALUES (?, ?, ?, ?, ?, ?)', [
					$username,
					$firstname,
					$lastname,
					$password,
					intval($age_obj['id']),
					$age
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
