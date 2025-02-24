<?php

class Config
{
    private static ?Config $instance = null;
    private array $settings = [];

    protected function __construct() {}

    public function setConfig(array $settings)
    {
        $this->settings = $settings;
    }

    public static function getInstance(): Config
    {
        if (self::$instance === null) {
            self::$instance = new Config();
        }

        return self::$instance;
    }

    public function __get($name)
    {
        switch($name){
            case "settings":
                return $this->settings;
            default:
                return $this->settings[$name];
        }
    }
}
