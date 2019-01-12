<?php

require_once __DIR__."/MinimalRouter.php";

$router = new Router(__DIR__."/routes");

$router->add_route(":index", "/Index.php");
$router->add_route("/ping", "/Ping.php");

if (!$router->handle_request())
{
    $router->throw();
}

?>