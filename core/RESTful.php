<?php

/**
 * Created by PhpStorm.
 * User: valentin
 * Date: 12/8/15
 * Time: 10:37 PM
 */
namespace PHPEASYRESTful;

use App\Auth;

class RESTful
{
	const
		R_GET		= 'GET',
		R_POST		= 'POST',
		R_PUT		= 'PUT',
		R_PATCH		= 'PATCH',
		R_DELETE	= 'DELETE',
		R_HEAD		= 'HEAD',
		R_OPTIONS	= 'OPTIONS';

	private
		$RequestType			= null,
		$Method					= 'Index',
		$Class					= 'Index',
		$RequireAuth			= true,
		$Params					= [],
		$RequiredParamsCount	= 0;

	private $ErrorDescription = null;

	public function __construct()
	{
		$this->parseURI();
		return $this;
	}

	private function parseURI()
	{
		$this->RequestType = $_SERVER['REQUEST_METHOD'];
		$uri = parse_url($_SERVER['REQUEST_URI']);
		$uri = explode('/', $uri['path']);

		if (
			!empty ($uri[1]) &&
			class_exists('App\\'.$uri[1])
		) {
			$this->Class = 'App\\'.$uri[1];
		} else {
			$this->Class = 'App\\Index';
		}

		if (!empty ($uri[2])) {
			try {
				$ref = new \ReflectionClass($this->Class);
				$prefix = 'get';
				if ($this->RequestType == self::R_POST) {
					$prefix = 'create';
				} elseif ($this->RequestType == self::R_DELETE) {
					$prefix = 'delete';
				} elseif ($this->RequestType == self::R_PUT) {
					$prefix = 'update';
				} elseif ($this->RequestType == self::R_PATCH) {
					$prefix = "update";
				}
				$m = $ref->getMethod($prefix.$uri[2]);
				$this->Method = $m->name;
				if (stristr($m->getDocComment(), '@auth false')) {
					$this->RequireAuth = false;
				}
				$this->RequiredParamsCount = $m->getNumberOfRequiredParameters();
				$params = $m->getParameters();
				if (is_array ($params)) {
					foreach ($params as $parameter) {
						$this->Params[] = $parameter->name;
					}
				}
			} catch (\ReflectionException $e) {
				echo 'Wrong Method Requested: '.$e->getMessage();
			}
		}
	}

	public function run()
	{
		try {
			if (!Auth::isAuthenticated() && $this->RequireAuth) {
				throw new RESTException(Error::AUTH_UNAUTHORIZED);
			}
			$output = $this->callObject()->getOutput();
			$redirect = $this->callObject()->getRedirect();
			if ($this->RequestType == self::R_POST) {
				http_response_code(201);
			}
			if ($redirect) {
				if ($this->RequestType == self::R_POST) {
					header('Location: '.$redirect, true, 201);
				}
			} elseif ($output !== null) {
				echo json_encode($output);
			}
		} catch (RESTException $e) {
			if ($e->getCode() == Error::AUTH_UNAUTHORIZED) {
				header('Location: /Auth/Session', true, 401);
				header('WWW-Authenticate: unknown');
				exit;
			} else {
				http_response_code(500);
			}
			echo json_encode([
				'error' => $e->getCode(),
				'message' => $e->getMessage(),
				'description' => $this->ErrorDescription,
			]);
		} catch (\PDOException $e) {
			echo $e->getMessage();
		}
	}

	private function callObject()
	{
		$obj = new $this->Class;
		if ($this->Params) {
			// Check for minimum amount of parameters
			if ($this->RequiredParamsCount > count($_POST)) {
				$RequiredParameters = '';
				foreach ($this->Params as $k=>$param) {
					if ($k>=$this->RequiredParamsCount) {
						break;
					}
					$RequiredParameters .= ($RequiredParameters?',':'').$param;
				}
				$this->ErrorDescription = 'Required Parameters: '.$RequiredParameters;
				throw new RESTException(Error::CORE_WRONG_PARAMETERS);
			}
			// Check for first required parameters to be passed
			$passParams = [];
			$MissedParameters = '';
			$checkedParameters = 0;
			foreach ($this->Params as $ParamName) {
				if (!isset ($_POST[$ParamName])) {
					if ($checkedParameters < $this->RequiredParamsCount) {
						$MissedParameters .= ($MissedParameters?', ':'').$ParamName;
					}
				} else {
					$passParams[] = $_POST[$ParamName];
					$checkedParameters++;
				}
			}
			if ($MissedParameters && count($passParams)) {
				$this->ErrorDescription = 'Missed Parameters: '.$MissedParameters;
				throw new RESTException(Error::CORE_WRONG_PARAMETERS);
			}
			call_user_func_array([$obj, $this->Method], $passParams);
		} else {
			$obj->{$this->Method}();
		}

		return $obj;
	}
}