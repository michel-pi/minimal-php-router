<?php

require_once __DIR__."/MinimalRouter.php";

$router = new Router(__DIR__."/routes");

$router->addRoute("/index", "/Index.php");
$router->addRoute("/ping", "/Ping.php");

$router->addRoute("/", "/Index.php"); // must be last (default)

if (!$router->handleRequest())
{
    $router->throw();
}

?>