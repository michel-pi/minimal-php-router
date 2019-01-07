<?php

class Route
{
    private $IsCompiled;

    public $Pattern;
    public $Controller;
    public $Class;

    public function __construct($pattern, $controller)
    {
        $this->Pattern = $pattern;
        $this->Controller = $controller;
        $this->Class = pathinfo($controller, PATHINFO_FILENAME);
    }

    public function matches($subject)
    {
        $regex = $this->compilePattern();

        return preg_match($regex, $subject);
    }

    private function compilePattern()
    {
        if ($this->IsCompiled) return $this->Pattern;

        $result = $this->Pattern;

        $result = rtrim($result, "/");

        $result = str_replace("/", "\\/", $result);

        $result = str_replace(":i", "[0-9]+", $result);
        $result = str_replace(":a", "[0-9A-Za-z]+", $result);
        $result = str_replace(":h", "[0-9A-Fa-f]+", $result);
        $result = str_replace(":c", "[a-zA-Z0-9+_\-\.]+", $result);

        $result = "@" . $result . "[\\/]?$@i";

        $this->Pattern = $result;
        $this->IsCompiled = true;

        return $result;
    }
}

class Router
{
    private $Routes;

    public $BaseRoute;

    public $Request;
    public $Response;

    public function __construct($route = false)
    {
        if ($route === false)
        {
            $this->BaseRoute = rtrim(__DIR__, "/") . "/controller";
        }
        else
        {
            $this->BaseRoute = rtrim($route, "/");
        }

        $this->Routes = array();
    }

    public function handleRequest()
    {
        $this->Request = new Request();
        $this->Response = new Response();

        $uri = parse_url($this->Request->Uri, PHP_URL_PATH);

        for ($i = 0; $i < count($this->Routes); $i++)
        {
            $route = $this->Routes[$i];

            if ($route->matches($uri))
            {
                include_once $this->BaseRoute . $route->Controller;

                $controller = new $route->Class();

                $controller->execute($this->Request, $this->Response);

                $this->Response->submit();

                return;
            }
        }

        $this->Response->ResponseCode = 400;
        $this->Response->Data = "invalid path";

        $this->Response->submit();
    }

    public function addRoute($pattern, $controller)
    {
        $this->Routes[] = new Route($pattern, $controller);
    }
}

class Request
{
    public $RemoteAddress;

    public $Uri;

    public $Method;
    public $UserAgent;

    public $Headers;
    public $Cookies;

    public $Data;

    public function __construct()
    {
        $this->RemoteAddress = $this->getRemoteAddress();

        $this->Uri = $_SERVER['REQUEST_URI'];
        $this->Method = $_SERVER['REQUEST_METHOD'];
        $this->UserAgent = $_SERVER['HTTP_USER_AGENT'];

        $this->Headers = $this->getHttpHeaders();
        $this->Cookies = $_COOKIE;

        if ($this->Method == "GET")
        {
            $this->Data = $_GET;
        }
        else if ($this->Method == "POST")
        {
            $this->Data = $_POST;
        }
        else
        {
            $this->Data = "";
        }
    }

    public function getRawData()
    {
        if ($this->Method == "POST")
        {
            return file_get_contents("php://input");
        }
        else
        {
            return parse_url($this->Uri, PHP_URL_QUERY);
        }
        
    }

    public function getData($name)
    {
        if (!empty($this->Data) && is_array($this->Data) && array_key_exists($name, $this->Data))
        {
            return $this->Data[$name];
        }
        else
        {
            return false;
        }
    }

    public function getHeader($name)
    {
        if (!empty($this->Headers) && is_array($this->Headers) && array_key_exists($name, $this->Headers))
        {
            return $this->Headers[$name];
        }
        else
        {
            return false;
        }
    }

    public function getCookie($name)
    {
        if (!empty($this->Cookies) && is_array($this->Cookies) && array_key_exists($name, $this->Cookies))
        {
            return $this->Cookies[$name];
        }
        else
        {
            return false;
        }
    }

    private function getHttpHeaders()
    {
        $headers = array();

        foreach ($_SERVER as $key => $value) {
            if ("HTTP_" != substr($key, 0, 5)) {
                continue;
            }

            $header = strtoupper(substr($key, 5));
            $headers[$header] = $value;
        }

        return $headers;
    }

    private function getRemoteAddress()
    {
        // cloudflare support
        // if (isset($_SERVER["HTTP_CF_CONNECTING_IP"]))
        // {
        //     $_SERVER["REMOTE_ADDR"] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        // }
        
        return $_SERVER["REMOTE_ADDR"];
    }
}

class Response
{
    private $Caching;
    private $CanSubmit;

    public $ResponseCode;
    public $ContentType;

    public $Headers;

    public $Data;

    public function __construct()
    {
        $this->CanSubmit = true;

        $this->ResponseCode = 200;
        $this->ContentType = "text/plain";
        $this->Data = "";

        $this->Headers = array();
        $this->Caching = array();
    }

    public function addHeader($header, $value = false)
    {
        if ($value === false)
        {
            $this->Headers[] = $header;
        }
        else
        {
            $this->Headers[] = $header . ": " . $value;
        }
        
    }

    public function addData($key, $value = false)
    {
        if (!is_array($this->Data))
        {
            $this->Data = array();
        }

        if ($value === false)
        {
            $this->Data[] = $key;
        }
        else
        {
            $this->Data[$key] = $value;
        }
    }

    public function setCookie($name, $value)
    {
        if ($value === false)
        {
            return setcookie($name);
        }
        else
        {
            return setcookie($name, $value);
        }
    }

    public function enableCaching($time = 86400, $isPublic = true)
    {
        if ($isPublic)
        {
            $this->Caching = ["Cache-Control: public, max-age=" . $time];
        }
        else
        {
            $this->Caching = ["Cache-Control: private, max-age=" . $time];
        }
    }

    public function disableCaching()
    {
        $this->Caching = ["Expires: 0", "Cache-Control: no-cache, no-store, must-revalidate, post-check=0, pre-check=0", "Pragma: no-cache"];
    }

    public function submit()
    {
        if ($this->CanSubmit)
        {
            $this->CanSubmit = false;

            http_response_code($this->ResponseCode);

            header("Content-Type: " . $this->ContentType);

            if (!empty($this->Headers) && is_array($this->Headers))
            {
                for ($i = 0; $i < count($this->Headers); $i++)
                {
                    header($this->Headers[$i]);
                }
            }

            if (!empty($this->Caching) && is_array($this->Caching))
            {
                for ($i = 0; $i < count($this->Caching); $i++)
                {
                    header($this->Caching[$i]);
                }
            }

            if (is_array($this->Data) && count($this->Data) > 0)
            {
                if ($this->isSequentialArray($this->Data))
                {
                    for ($i = 0; $i < count($this->Data) - 1; $i++)
                    {
                        echo $i . "=" . $this->Data[$i] . "&";
                    }

                    $tmp = count($this->Data) - 1;

                    echo $tmp . "=" . $this->Data[$tmp];
                }
                else
                {
                    $keys = array_keys($this->Data);

                    for ($i = 0; $i < count($keys) - 1; $i++)
                    {
                        echo $keys[$i] . "=" . $this->Data[$keys[$i]] . "&";
                    }

                    $tmp = count($keys) - 1;

                    echo $keys[$tmp] . "=" . $this->Data[$keys[$tmp]];
                }
            }
            else if (is_array($this->Data))
            {
                echo "";
            }
            else
            {
                echo $this->Data;
            }

            return true;
        }
        else
        {
            return false;
        }
    }

    private function isSequentialArray($array)
    {
        if (!empty($array) || !is_array($array) || count($array) == 0)
        {
            return true;
        }

        $keys = array_keys($array);
        $filter = array_filter($keys, "is_string");
        
        return count($filter) == 0;
    }
}

?>