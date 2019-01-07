<?php

class Ping
{
    public function execute(&$request, &$response)
    {
        $response->ResponseCode = 200;
        $response->Data = time();

        $response->disableCaching();
    }
}

?>