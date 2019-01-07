<?php

class Index
{
    public function execute(&$request, &$response)
    {
        $response->ResponseCode = 200;
        $response->Data = "Hello World";

        $response->enableCaching();
    }
}

?>