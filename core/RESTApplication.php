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
    private $Logger;

    public function __construct()
    {
        $this->Logger = new class {
            public function __call($name, $args){}
        };
    }

    public function setLogger($LoggerObject)
    {
        $this->Logger = $LoggerObject;
    }

    public function getLogger()
    {
        return $this->Logger;
    }

    public function getOutput()
    {
        return $this->Output;
    }

    public function getRedirect()
    {
        return $this->Redirect;
    }
}
