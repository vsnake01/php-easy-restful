<?php
/**
 * Created by PhpStorm.
 * User: valentin
 * Date: 6/17/15
 * Time: 9:05 PM
 */

namespace PHPEASYRESTful;

class ErrorMessage
{
	public $Error = [
		Error::CORE_WRONG_OBJECT_REQUESTED => 'Wrong Object Requested',
		Error::CORE_WRONG_PARAMETERS => 'Wrong Parameters Passed',

		Error::AUTH_UNAUTHORIZED => 'Unauthorized',
	];
}

class Error
{
	const CORE_WRONG_OBJECT_REQUESTED = 1001;
	const CORE_WRONG_PARAMETERS = 1002;

	const AUTH_UNAUTHORIZED = 2001;
	const AUTH_WRONG_CREDENTIALS = 2002;
	const AUTH_USER_CREATE = 2003;
}

class Exception extends \Exception
{
	public function __construct($Error)
	{
		parent::__construct((new ErrorMessage)->Error[$Error], $Error);
	}
}

