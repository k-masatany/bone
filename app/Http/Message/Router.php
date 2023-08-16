<?php

namespace App\Http\Message;

class Router {

    private string $method;
    private string $path;

    public function __construct() {
        $this->method = $this->getMethod();
        $this->path = $this->getPath();
    }

    private function getMethod() {
        return $_SERVER['REQUEST_METHOD'];
    }

    private function getPath() {
        return $_SERVER['REQUEST_URI'];
    }
}
