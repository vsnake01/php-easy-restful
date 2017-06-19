<?php
/**
 * Created by PhpStorm.
 * User: valentin
 * Date: 5/25/17
 * Time: 8:36 PM
 */

namespace PHPEASYRESTful;

class RESTApplication
{
    protected $Output = null;
    protected $Redirect = null;

    public function __construct(){}

    public function getOutput()
    {
        return $this->Output;
    }

    public function getRedirect()
    {
        return $this->Redirect;
    }
}