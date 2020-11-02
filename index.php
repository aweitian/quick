<?php
class Router
{
    public $default_control = 'main';
    public $default_action = 'index';
    public $path;
    public $control;
    public $action;
    public $view;
    public function __construct()
    {
        $this->path = $this->requestUri();
        $this->view = new View(__DIR__."/view");
    }

    public function run()
    {
        $this->getAction();
        // var_dump($this->control,$this->action);exit;
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
        $ctl = $this->control;
        $act = $this->action;

        $cls = ucfirst(strtolower($ctl));
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

                )
            );
            if ($privilege !== true) {
                $this->_403();
                return;
            }
        }

        if ($rc->hasMethod($act)) {
            if ($rc->hasMethod("__construct")) {
                $controller = $rc->newInstance();
            } else {
                $controller = $rc->newInstance();
            }
            $method = $rc->getMethod($act);
            $method->invokeArgs($controller, [$this->view]);
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


class View
{
    protected $ext_path = '';
    protected $viewFile = '';
    protected $inc_File = '';
    /**
     * All of the finished, captured sections.
     *
     * @var array
     */
    protected $sections = array();

    /**
     * 可以用于SECTION 中 嵌套 SECTION
     * 用于END SECTION时知道是哪个SECTION
     * The stack of in-progress sections.
     *
     * @var array
     */
    protected $sectionStack = array();

    /***
     * @var array
     */
    protected $yields = array();
    /**
     * The parent placeholder for the request.
     *
     * @var array
     */
    protected static $parentPlaceholder = array();

    protected $tpl_dir;

    protected $data = array();

    public $response = '';

    public function __construct($tpl_dir)
    {
        $this->tpl_dir = $tpl_dir;
    }

    /**
     * PATH可以是index/index or index  or index.php
     * @param $path
     * @param bool $isAbsPath
     */
    public function extend($path, $isAbsPath = false)
    {
        $path = $this->fixPath($path, $isAbsPath);
        $this->ext_path = $path;
        if (file_exists($this->ext_path)) {
            extract($this->data);
            ob_start();
            include $this->ext_path;
            $this->response = ob_get_clean();
        }
        else
        {
            echo "<!-- {$this->ext_path} is nonexist -->";
        }
    }

    /**
     * @param $path
     * @param bool $isAbsPath
     */
    public function includeFile($path, $isAbsPath = false)
    {
        $this->inc_File = $this->fixPath($path, $isAbsPath);
        if (file_exists($this->inc_File)) {
            extract($this->data);
            include $this->inc_File;
        }

    }

    /**
     * @param $path
     * @param $isAbsPath
     * @return string
     */
    public function fixPath($path, $isAbsPath)
    {
        if ($isAbsPath) return $path;

        return $this->tpl_dir . (substr($this->tpl_dir, -1, 1) == '/' ? '' : '/') . $path . (substr($path,-4,4) == '.php' ? '' : '.php');
    }

    /**
     * Start injecting content into a section.
     *
     * @param  string $section
     * @param  string|null $content
     * @return void
     */
    public function start($section, $content = null)
    {
        if ($content === null) {
            if (ob_start()) {
                $this->sectionStack[] = $section;
            }
        } else {
            $this->extendSection($section, $content);
        }
    }

    /**
     * Stop injecting content into a section.
     *
     * @param  bool $overwrite
     * @return string
     * @throws \InvalidArgumentException
     */
    public function end($overwrite = true)
    {
        if (empty($this->sectionStack)) {
            throw new InvalidArgumentException('Cannot end a section without first starting one.');
        }

        $last = array_pop($this->sectionStack);

        if ($overwrite) {
            $this->sections[$last] = ob_get_clean();
        } else {
            $this->extendSection($last, ob_get_clean());
        }

        return $last;
    }

    /**
     * Stop injecting content into a section and append it.
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function appendSection()
    {
        if (empty($this->sectionStack)) {
            throw new InvalidArgumentException('Cannot end a section without first starting one.');
        }

        $last = array_pop($this->sectionStack);

        if (isset($this->sections[$last])) {
            $this->sections[$last] .= ob_get_clean();
        } else {
            $this->sections[$last] = ob_get_clean();
        }

        return $last;
    }

    /**
     * Append content to a given section.
     *
     * @param  string $section
     * @param  string $content
     * @param bool $prepend
     * @return void
     */
    protected function extendSection($section, $content, $prepend = true)
    {
        if (isset($this->sections[$section])) {
            if ($prepend) {
                $content = $this->sections[$section] . $content;
            } else {
                $content = $content . $this->sections[$section];
            }
        }

        $this->sections[$section] = $content;
    }

    /**
     * Get the string contents of a section.
     *
     * @param  string $section
     * @param  string $default
     */
    public function yieldContent($section, $default = '')
    {
        if (!$this->hasSection($section))
            $this->sections[$section] = $default;
        $this->yields[$section] = static::parentPlaceholder($section);
        print $this->yields[$section];
    }

    /**
     * Get the parent placeholder for the current request.
     *
     * @param  string $section
     * @return string
     */
    public static function parentPlaceholder($section = '')
    {
        if (!isset(static::$parentPlaceholder[$section])) {
            static::$parentPlaceholder[$section] = '##parent-placeholder-' . sha1($section) . '##';
        }

        return static::$parentPlaceholder[$section];
    }

    /**
     * Check if section exists.
     *
     * @param  string $name
     * @return bool
     */
    public function hasSection($name)
    {
        return array_key_exists($name, $this->sections);
    }

    /**
     * Get the contents of a section.
     *
     * @param  string $name
     * @param  string $default
     * @return mixed
     */
    public function getSection($name, $default = null)
    {
        return isset($this->sections[$name]) ? $this->sections[$name] : $default;
    }

    /**
     * Get the entire array of sections.
     *
     * @return array
     */
    public function getSections()
    {
        return $this->sections;
    }

    /**
     * Flush all of the sections.
     *
     * @return void
     */
    public function flushSections()
    {
        $this->sections = array();
        $this->sectionStack = array();
    }

    /**
     * PATH可以是index/index or index  or index.php
     * Get the evaluated contents of the object.
     *
     * @param $path
     * @param bool $isAbsPath
     * @return string
     */
    public function render($path,$isAbsPath = false)
    {
        $this->viewFile = $this->fixPath($path,$isAbsPath);
        if (file_exists($this->viewFile)) {
            extract($this->data);
            ob_start();
            include $this->viewFile;
            $this->response .= ob_get_clean();
        } else {
            return "{$this->viewFile} is not exists";
        }
        $placeholders = array();
        foreach ($this->yields as $key => $value) {
            $placeholders[$value] = $this->sections[$key];
        }
        return strtr($this->response, $placeholders);
    }

    /**
     * Get the name of the view.
     *
     * @return string
     */
    public function name()
    {
        return $this->viewFile;
    }

    /**
     * Add a piece of data to the view.
     *
     * @param  string|array $key
     * @param  mixed $value
     * @return $this
     */
    public function with($key, $value = null)
    {
        $this->data[$key] = $value;
        return $this;
    }
}

$router = new Router();
$router->run();