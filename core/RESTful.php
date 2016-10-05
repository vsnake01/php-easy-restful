<?php

/**
 * Created by PhpStorm.
 * User: valentin
 * Date: 12/8/15
 * Time: 10:37 PM
 */
namespace PHPEASYRESTful;

use App\Auth;
use PHPEASYRESTful;

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

    const
        OPT_NAMESPACE   = 'OPT_NAMESPACE',
        OPT_INDEXCLASS  = 'OPT_INDEXCLASS',
        OPT_AUTHCLASS   = 'OPT_AUTHCLASS';
    
    private $Options = [
        self::OPT_NAMESPACE => 'App',
        self::OPT_AUTHCLASS => 'Auth',
        self::OPT_INDEXCLASS => 'Index',
    ];

	private $ErrorDescription = null;

	public function __construct(Array $Options)
	{
	    foreach ($Options as $k=>$v) {
	        if (array_key_exists($k, $this->Options)) {
	            $this->Options[$k] = $v;
            }
        }
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
			class_exists($this->Options[self::OPT_NAMESPACE].'\\'.$uri[1])
		) {
			$this->Class = $this->Options[self::OPT_NAMESPACE].'\\'.$uri[1];
		} else {
			$this->Class = $this->Options[self::OPT_NAMESPACE].'\\'.$this->Options[self::OPT_INDEXCLASS];
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
		    $authClass = $this->Options[self::OPT_NAMESPACE].'\\Auth';
			if (!$authClass::isAuthenticated() && $this->RequireAuth) {
				throw new Exception(Error::AUTH_UNAUTHORIZED);
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
		} catch (Exception $e) {
			if ($e->getCode() == Error::AUTH_UNAUTHORIZED) {
				header('Location: /Auth/Session', true, 401);
				header('WWW-Authenticate: unknown');
				exit;
			} else {
				http_response_code(400);
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
				throw new Exception(Error::CORE_WRONG_PARAMETERS);
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
				throw new Exception(Error::CORE_WRONG_PARAMETERS);
			}
			call_user_func_array([$obj, $this->Method], $passParams);
		} else {
			$obj->{$this->Method}();
		}

		return $obj;
	}
}
