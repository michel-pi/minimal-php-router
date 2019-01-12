<?php

class Index
{
    public function execute(&$request, &$response)
    {
        $response->StatusCode = 200;
        $response->Data = "Hello World";

        $response->enable_caching();
    }
}

?>