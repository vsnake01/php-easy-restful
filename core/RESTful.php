<?php

/**
 * Created by PhpStorm.
 * User: valentin
 * Date: 12/8/15
 * Time: 10:37 PM
 */

namespace PHPEASYRESTful;

use Propel\Runtime\Exception\PropelException;

class RESTful
{
    const
        R_GET = 'GET',
        R_POST = 'POST',
        R_PUT = 'PUT',
        R_PATCH = 'PATCH',
        R_DELETE = 'DELETE',
        R_HEAD = 'HEAD',
        R_OPTIONS = 'OPTIONS',
        F_AUTH = 'PHPEASYRESTFUL_AUTHENTICATED';

    private
        $RequestType = null,
        $Method = 'Index',
        $Class = 'Index',
        $RequireAuth = false,
        $Params = [],
        $RequiredParamsCount = 0;

    private $ErrorDescription = null;

    private $Logger;

    private $ApplicationAuthenticationClassName = '\App\Auth';
    private $ApplicationAuthenticationMethodName = 'isAuthenticated';
    private $ApplicationAuthenticated = false;
    private $ApplicationNamespace;
    private $LoggerClassName;

    public function __construct()
    {
        try {
            $this->ApplicationAuthenticated = $this->isAuthenticated();
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage(),
            ]);
            exit;
        }
        return $this;
    }

    /**
     * @return bool
     */
    private function isAuthenticated(): bool
    {
        $className = $this->ApplicationNamespace . $this->ApplicationAuthenticationClassName;
        return $className::{$this->ApplicationAuthenticationMethodName}()
            ? true
            : false;
    }

    /**
     * @param $className
     * @return RESTful
     */
    public function setAuthenticationClassName($className): RESTful
    {
        $this->ApplicationAuthenticationClassName = $className;
        return $this;
    }

    /**
     * @param $methodName
     * @return RESTful
     */
    public function setAuthenticationMethodName($methodName): RESTful
    {
        $this->ApplicationAuthenticationMethodName = $methodName;
        $this->RequireAuth = true;
        return $this;
    }

    /**
     * @param $LoggerObject
     * @return RESTful
     */
    public function setLogger($LoggerObject): RESTful
    {
        $this->Logger = $LoggerObject;
        return $this;
    }

    public function getLogger()
    {
        if (!is_object($this->Logger)) {
            $this->Logger = new class
            {
                public function __call($name, $arguments)
                {
                    switch ($name) {
                        case 'warn':
                        case 'warning':
                            $level = LOG_WARNING;
                            break;
                        case 'debug':
                            $level = LOG_DEBUG;
                            break;
                        case 'error':
                            $level = LOG_ERR;
                            break;
                        case 'fatal':
                            $level = LOG_CRIT;
                            break;
                        case 'all':
                            $level = LOG_INFO;
                            break;
                        case 'info':
                        case 'trace':
                        case 'all':
                        default:
                            $level = LOG_INFO;
                    }

                    openlog(php_sapi_name(), LOG_PID, LOG_LOCAL0);
                    syslog($level, json_encode($arguments));
                    closelog();
                    
                    if ($level == LOG_ERR || $level == LOG_CRIT) {
                        error_log(json_encode($arguments));
                    }
                }
            };
        }
        return $this->Logger;
    }

    /**
     * @return string
     */
    public function getApplicationNameSpace(): string
    {
        return $this->ApplicationNameSpace;
    }

    /**
     * @param string $ApplicationNameSpace
     * @return RESTful
     */
    public function setApplicationNameSpace(string $ApplicationNameSpace): RESTful
    {
        $this->ApplicationNameSpace = $ApplicationNameSpace;
        return $this;
    }

    private function parseURI()
    {
        $this->RequestType = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI']);
        $uri = explode('/', $uri['path']);

        $this->Class = 'Index';

        if (!empty($uri[1])) {
            $this->Class = $this->getApplicationNameSpace() . '\\' . $uri[1];
        }

        $collectionRequest = false;

        if (!class_exists($this->Class)) {
            // Let's check for collections request
            if (substr($this->Class, -1) == 's') {
                $class = substr($this->Class, 0, -1);
                if (class_exists(($class))) {
                    $this->Class = $class;
                    $collectionRequest = true;
                }
            }
            if (substr($this->Class, -2) == 'es') {
                $class = substr($this->Class, 0, -2);
                if (class_exists(($class))) {
                    $this->Class = $class;
                    $collectionRequest = true;
                }
            }
        }

        if (!empty ($uri[2]) || $collectionRequest) {
            try {
                $ref = new \ReflectionClass($this->Class);

                if ($collectionRequest) {
                    $m = $ref->getMethod('getCollection');
                } else {
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
                    $m = $ref->getMethod($prefix . $uri[2]);
                }

                $this->Method = $m->name;

                if (stristr($m->getDocComment(), '@auth false')) {
                    $this->RequireAuth = false;
                }
                $this->RequiredParamsCount = $m->getNumberOfRequiredParameters();
                $params = $m->getParameters();
                if (is_array($params)) {
                    foreach ($params as $parameter) {
                        $this->Params[] = $parameter->name;
                    }
                }
            } catch (\ReflectionException $e) {

                http_response_code(404);
                echo json_encode([
                    'Error' => $e->getCode(),
                    'Message' => $e->getMessage(),
                ]);
                exit;
            }
        }
    }

    public function run()
    {
        try {
            $this->parseURI();
            if ($this->RequireAuth && !$this->ApplicationAuthenticated) {
                throw new Exception(Error::AUTH_UNAUTHORIZED);
            }
            $obj = $this->callObject();
            $output = $obj->getOutput();
            $redirect = $obj->getRedirect();
            if ($this->RequestType == self::R_POST) {
                http_response_code(201);
            }
            if ($redirect) {
                if ($this->RequestType == self::R_POST) {
                    header('Location: ' . $redirect, true, 201);
                }
            } elseif ($output !== null) {
                echo json_encode($output);
            }
        } catch (\Error $e) {
            $this->getLogger()->error($e->getMessage());
            $this->finalOutput(
                json_encode([
                    'SysError' => $e->getCode(),
                    'Message' => $e->getMessage(),
                    'Description' => $this->ErrorDescription,
                ]),
                500
            );
        } catch (Exception $e) {
            $this->getLogger()->error($e->getMessage());
            if ($e->getCode() == Error::AUTH_UNAUTHORIZED) {
                header('Location: /Auth/Session', true, 401);
                header('WWW-Authenticate: unknown');
                exit;
            } else {
                http_response_code(400);
            }

            $this->finalOutput(
                json_encode([
                    'SysError' => $e->getCode(),
                    'Message' => $e->getMessage(),
                    'Description' => $this->ErrorDescription,
                ])
            );
        } catch (\PDOException $e) {
            $this->getLogger()->error($e->getMessage());
            http_response_code(500);
        } catch (PropelException $e) {
            $this->getLogger()->error($e->getMessage());
            http_response_code(500);
        } catch (\App\Exception $e) {
            $this->getLogger()->error($e->getMessage());
            http_response_code($e->getHttpCode());
            $this->finalOutput(
                json_encode([
                    'AppError' => $e->getCode(),
                    'Message' => $e->getMessage(),
                ])
            );
        }
    }

    private function callObject()
    {
        $className = $this->Class;
        $incomeParams = ($this->RequestType == self::R_GET ? $_GET : $_POST);

        $obj = new $className;
        if ($this->Params) {
            // Check for minimum amount of parameters

            if (
                $this->RequiredParamsCount > count($incomeParams)
            ) {
                $RequiredParameters = '';
                foreach ($this->Params as $k => $param) {
                    if ($k >= $this->RequiredParamsCount) {
                        break;
                    }
                    $RequiredParameters .= ($RequiredParameters ? ',' : '') . $param;
                }
                $this->ErrorDescription = 'Required Parameters: ' . $RequiredParameters;
                throw new Exception(Error::CORE_WRONG_PARAMETERS);
            }
            // Check for first required parameters to be passed
            $passParams = [];
            $MissedParameters = '';
            $checkedParameters = 0;
            foreach ($this->Params as $ParamName) {
                if (!isset ($incomeParams[$ParamName])) {
                    if ($checkedParameters < $this->RequiredParamsCount) {
                        $MissedParameters .= ($MissedParameters ? ', ' : '') . $ParamName;
                    }
                } else {
                    $passParams[] = $incomeParams[$ParamName];
                    $checkedParameters++;
                }
            }
            if ($MissedParameters && !empty($incomeParams)) {
                $this->ErrorDescription = 'Missed Parameters: ' . $MissedParameters;
                throw new Exception(Error::CORE_WRONG_PARAMETERS);
            }

            $obj->setLogger($this->getLogger());

            call_user_func_array([$obj, $this->Method], $passParams);

        } else {
            $obj->{$this->Method}();
        }

        return $obj;
    }

    private function finalOutput(string $Output, int $HTTPCode = null)
    {
        if ($HTTPCode) {
            http_response_code($HTTPCode);
        }
        echo $Output;
        exit;
    }
}
