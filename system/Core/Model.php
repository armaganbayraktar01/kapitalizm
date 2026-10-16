<?php
namespace System\Core;

class Model {
    protected function db() {
        return Database::getInstance();
    }
}