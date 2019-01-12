<?php

class Ping
{
    public function execute(&$request, &$response)
    {
        $response->StatusCode = 200;
        $response->Data = time();

        $response->disable_caching();
    }
}

?>