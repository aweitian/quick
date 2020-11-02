<?php
class Router
{
    public $namespace = "\\app\\Controller\\";
    public $default_control = 'main';
    public $default_action = 'index';
    public $path;
    public $control;
    public $action;
    public function __construct()
    {
        $this->path = $this->requestUri();
    }

    public function run()
    {
        $this->getAction();
        $this->dispatch();
    }
    private function getAction()
    {
        $path = trim($this->path, '/');
        $arr = explode('/', $path);
        switch (count($arr)) {
            case 0:
                $ctl = $this->default_control;
                $act = $this->default_action;
                break;
            case 1:
                $ctl = $arr[0];
                $act = $this->default_action;
                $ctl = preg_replace("/[^\w]/", "", $ctl);
                if ($ctl == "") {
                    $ctl = $this->default_control;
                }
                break;
            default:
                $ctl = $arr[0];
                $act = $arr[1];
                $ctl = preg_replace("/[^\w]/", "", $ctl);
                if ($ctl == "") {
                    $ctl = $this->default_control;
                }
                $act = preg_replace("/[^\w]/", "", $act);
                if ($act == "") {
                    $act = $this->default_action;
                }
                break;
        }
        $this->control = $ctl;
        $this->action = $act;
    }

    private function dispatch()
    {
        $input = array();
        $namespace = $this->namespace;
        $ctl = $this->control;
        $act = $this->action;
        if (!is_null($namespace)) {
            $this->namespace = $namespace;
        }

        $cls = $this->namespace . ucfirst(strtolower($ctl));
        if (!class_exists($cls)) {
            if(file_exists(__DIR__."/app/{$this->control}.php")) {
                require __DIR__."/app/{$this->control}.php";
            }
        }
        if (!class_exists($cls)) {
            $this->_404();
            return;
        }
        $rc = new \ReflectionClass($cls);

        if ($rc->hasMethod('check') && $rc->getMethod('check')->isStatic()) {
            $privilege = call_user_func_array(
                $cls . "::check",
                array(
                    $this->container
                )
            );
            if ($privilege !== true) {
                $this->_403();
                return;
            }
        }

        if ($rc->hasMethod($act)) {
            if ($rc->hasMethod("__construct")) {
                $controller = $rc->newInstance($this->container);
            } else {
                $controller = $rc->newInstance();
            }
            $method = $rc->getMethod($act);
            $method->invokeArgs($controller, $input);
        } else {
            $this->_404();
            return;
        }
    }

    public function requestUri(){
		if (isset($_SERVER['HTTP_X_REWRITE_URL'])){
			$uri = $_SERVER['HTTP_X_REWRITE_URL'];
		}elseif (isset($_SERVER['REQUEST_URI'])){
			$uri = $_SERVER['REQUEST_URI'];
		}elseif (isset($_SERVER['ORIG_PATH_INFO'])){
			$uri = $_SERVER['ORIG_PATH_INFO'];
			if (! empty($_SERVER['QUERY_STRING'])){
				$uri .= '?' . $_SERVER['QUERY_STRING'];
			}
		}else{
			$uri = '';
		}
		return $uri;
	}

    public function _404()
    {
        header("HTTP/1.1 404 Not Found");  
        header("Status: 404 Not Found");  
        exit;  
    }
    public function _403()
    {
        header("HTTP/1.1 403 Permission denied");  
        header("Status: 403 Permission denied");  
        exit;  
    }
}

$router = new Router();
$router->run();