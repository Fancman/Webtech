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
		$task_state = $request->query->get('task_state');

		$response = new Response();

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');

		if($params){
			$user_id = is_null($user_id) ? (array_key_exists("user_id", $params) ? $params["user_id"] : null) : $user_id;
			$task_id = is_null($task_id) ? (array_key_exists("task_id", $params) ? $params["task_id"] : null) : $task_id;
			$task_state = is_null($task_state) ? (array_key_exists("task_state", $params) ? $params["task_state"] : null) : $task_state;

			if($task_state && !in_array($task_state, ['rozpracovane', 'splnene', 'nesplnene'])){
				$response->setStatusCode(Response::HTTP_BAD_REQUEST);

				$response->setContent(json_encode([
					'msg' => 'Task state musi byt z predvelenych hodnot: ' .  json_encode(['rozpracovane', 'splnene', 'nesplnene']),
				]));

				return $response;
			}
		}

		if(isset($user_id)
		&& isset($task_id)
		&& is_null($task_state)){
			try {
				$stmt = $connection->prepare("INSERT INTO new_task_progress (user_id, task_id, state) VALUES (:user_id, :task_id, 'rozpracovane')");
        		$stmt->execute(
					[
						'user_id' => $user_id,
						'task_id' => $task_id,
					]
				);


				$created_id = $connection->lastInsertId();

			} catch (\Throwable $th) {
				$response->setStatusCode(Response::HTTP_BAD_REQUEST);

				$response->setContent(json_encode([
					'msg' => 'Could not be inserted into DB.',
					'error' => $th->getMessage()
				]));

				return $response;
			}

			$resultSet = $connection->executeQuery('SELECT * FROM new_task WHERE id = ?', [
				$task_id
			]);

			$task_record = $resultSet->fetchAllAssociative();

			$response->setContent(json_encode([
				'new_record_id' => $created_id,
				'activity_id' => ($task_record ? $task_record[0]['activity_id'] : null),
				'task_id' => $task_id
			]));

			$response->setStatusCode(Response::HTTP_OK);

			return $response;
		}

		if(isset($user_id)
		&& isset($task_id)
		&& isset($task_state)){
			try {
				$stmt = $connection->prepare("UPDATE new_task_progress SET state = :task_state WHERE user_id = :user_id AND task_id = :task_id");
        		$execute = $stmt->execute(
					[
						'task_state' => $task_state,
						'user_id' => $user_id,
						'task_id' => $task_id,
					]
				);

				$row_count = $execute->rowCount();

			} catch (\Throwable $th) {
				$response->setStatusCode(Response::HTTP_BAD_REQUEST);

				$response->setContent(json_encode([
					'msg' => 'Could not be update in DB.',
					'error' => $th->getMessage()
				]));

				return $response;
			}

			$resultSet = $connection->executeQuery('SELECT * FROM new_task WHERE id = ?', [
				$task_id
			]);

			$task_record = $resultSet->fetchAllAssociative();

			$response->setContent(json_encode([
				'activity_id' => ($task_record ? $task_record[0]['activity_id'] : null),
				'task_id' => $task_id,
				'affected_rows' => $row_count
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
     * @Route("/api/active", name="active")
     */
    public function active(Connection $connection, Request $request): Response
    {
		$request_content = strval($request->getContent());
    	$params = json_decode($request_content, true);

		$user_id = $request->query->get('user_id');

		$response = new Response();

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');

		if($params){
			$user_id = is_null($user_id) ? (array_key_exists("user_id", $params) ? $params["user_id"] : null) : $user_id;
		}

		if(isset($user_id)){
			try {
				$resultSet = $connection->executeQuery('select q1.*, na.name as age_activity_name from (SELECT a.* FROM new_task_progress p
				left join new_task t ON p.task_id = t.id
				left JOIN new_activity a ON t.activity_id = a.id
				WHERE user_id = ?
				group by a.id, a.name, a.img_url, a.age_category_id, a.level, a.activity_type) q1
				left join new_age_category na on q1.age_category_id = na.id', [
					$user_id
				]);

				$users_activities = $resultSet->fetchAllAssociative();

			} catch (\Throwable $th) {
				$response->setStatusCode(Response::HTTP_BAD_REQUEST);

				$response->setContent(json_encode([
					'msg' => 'Could not be inserted into DB.',
					'error' => $th->getMessage()
				]));

				return $response;
			}

			foreach ($users_activities as $u => $ua) {
				$resultSet = $connection->executeQuery("select nt2.*,
				CASE
					WHEN ntp1.state = 'splnene' THEN 'splnene'
					WHEN ntp1.state = 'rozpracovane' THEN 'rozpracovane'
					ELSE 'nesplnene'
				END as task_state from (select distinct nt.activity_id from new_task_progress ntp
				left join new_task nt on ntp.task_id=nt.id
				where user_id = ?) q1
				JOIN new_task nt2 ON q1.activity_id = nt2.activity_id
				left join (select * from new_task_progress ntp0 where ntp0.user_id = ?) ntp1 on nt2.id = ntp1.task_id
				where nt2.activity_id = ?
				", [
					$user_id,
					$user_id,
					$ua['id']
				]);

				$activity_tasks = $resultSet->fetchAllAssociative();

				if($activity_tasks){
					$users_activities[$u]['tasks'] = $activity_tasks;
				}
			}

			$response->setContent(json_encode($users_activities));

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
     * @Route("/api/add-activity", name="add-activity")
     */
    public function add_activity(Connection $connection, Request $request): Response
    {
		$request_content = strval($request->getContent());
    	$params = json_decode($request_content, true);

		$user_id = $request->query->get('user_id');
		$activity_id = $request->query->get('activity_id');

		$response = new Response();

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');

		if($params){
			$user_id = is_null($user_id) ? (array_key_exists("user_id", $params) ? $params["user_id"] : null) : $user_id;
			$activity_id = is_null($activity_id) ? (array_key_exists("activity_id", $params) ? $params["activity_id"] : null) : $activity_id;
		}

		$completed_activities = [];

		if(isset($user_id) && isset($activity_id)){

			$row_counts = 0;

			$resultSet = $connection->executeQuery('SELECT * FROM new_task WHERE activity_id = ?', [
				$activity_id
			]);

			$ulohy = $resultSet->fetchAllAssociative();

			foreach ($ulohy as $u) {
				try {
					$stmt = $connection->prepare('INSERT INTO new_task_progress (user_id, task_id, state) VALUES (?, ?, ?)');

					$execute = $stmt->execute([
						$user_id,
						$u['id'],
						'rozpracovane'
					]);
	
					$row_count = $execute->rowCount();
					$row_counts += $row_count;
				} catch (\Throwable $th) {
					//echo $th->getMessage();
				}
			}

			$response->setContent(json_encode(['message' => 'Inserted '.$row_counts.' rows.']));

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
     * @Route("/api/completed", name="completed")
     */
    public function completed(Connection $connection, Request $request): Response
    {
		$request_content = strval($request->getContent());
    	$params = json_decode($request_content, true);

		$user_id = $request->query->get('user_id');

		$response = new Response();

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');

		if($params){
			$user_id = is_null($user_id) ? (array_key_exists("user_id", $params) ? $params["user_id"] : null) : $user_id;
		}

		$completed_activities = [];

		if(isset($user_id)){
			try {
				$resultSet = $connection->executeQuery("
				select DISTINCT na.id from new_task_progress ntp
				LEFT join new_task nt ON nt.id=ntp.task_id
				LEFT join new_activity na on nt.activity_id=na.id
				WHERE ntp.state = 'splnene'
				AND ntp.user_id = ?", [
					$user_id
				]);

				$users_activities = $resultSet->fetchAllAssociative();

			} catch (\Throwable $th) {
				$response->setStatusCode(Response::HTTP_BAD_REQUEST);

				$response->setContent(json_encode([
					'msg' => 'Could not be inserted into DB.',
					'error' => $th->getMessage()
				]));

				return $response;
			}

			foreach ($users_activities as $u => $ua) {
				$activity_id = $ua['id'];

				$resultSet = $connection->executeQuery("select ntp.task_id from new_task_progress ntp
				LEFT join new_task nt ON nt.id=ntp.task_id
				LEFT join new_activity na on nt.activity_id=na.id
				WHERE ntp.state = 'splnene'
				AND ntp.user_id = ?
				AND nt.activity_id = ?
				", [
					$user_id,
					$activity_id
				]);

				$completed_tasks = $resultSet->fetchAllAssociative();
				$completed_tasks_ids = [];
				$all_tasks_ids = [];

				foreach ($completed_tasks as $task) {
					$completed_tasks_ids[] = $task['task_id'];
				}

				$resultSet = $connection->executeQuery("SELECT * FROM new_task WHERE activity_id = ?", [
					$activity_id
				]);

				$all_tasks = $resultSet->fetchAllAssociative();

				foreach ($all_tasks as $task) {
					$all_tasks_ids[] = $task['id'];
				}

				$result = array_diff($all_tasks_ids, $completed_tasks_ids);

				/*print_r(json_encode($completed_tasks_ids));
				echo "<br>";
				print_r(json_encode($all_tasks_ids));
				echo "<br>";
				echo count($result);
				echo "<br>";
				echo "<br>";*/

				if(count($result) == 0){
					$resultSet = $connection->executeQuery('SELECT na.id, na.name, na.img_url, na.level, na.activity_type, nac.id as age_category_id, nac.name as age_category
					FROM new_activity na LEFT JOIN new_age_category nac ON na.age_category_id=nac.id
					WHERE 1=1 AND na.id = ?', [
							$activity_id
					]);

					$aktivita = $resultSet->fetchAllAssociative()[0];

					$resultSet = $connection->executeQuery('SELECT * FROM new_task WHERE activity_id = ?', [
						$activity_id
					]);

					$aktivita['ulohy'] = $resultSet->fetchAllAssociative();

					$completed_activities[] = $aktivita;
				}
			}

			//die();

			$response->setContent(json_encode($completed_activities));

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
