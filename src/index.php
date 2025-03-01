<?php

include_once 'test.php';
include_once 'config.php';

class Index
{
    private string $dirTests = "/tests";
    private string $nameFileConfig = ".setupTests.json";

    public function __construct()
    {
        header('Content-Type: application/json');
    }

    public function __toString(): string
    {
        return $this->handleResponse($this->runTests());
    }

    private function handleResponse(mixed $return): string
    {
        if (is_array($return)) {
            return json_encode($return);
        } else {
            return (string) $return;
        }
    }

    private function getTests(): array
    {
        $dir = $this->dirTests;
        $files = scandir($dir);
        $jsonFiles = array_filter($files, function ($file) use ($dir) {
            return is_file($dir . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'json';
        });
        return array_values($jsonFiles); // Reindexa o array
    }

    private function runTests(): array
    {
        $files = $this->getTests();
        $results = [];
        foreach ($files as $fileName) {
            if ($fileName == $this->nameFileConfig) {
                $config = Config::getInstance();
                $config->setConfig($this->getJsonFile($fileName));
                continue;
            }

            $tests = $this->getJsonFile($fileName);

            foreach ($tests as $test) {
                $test = new Test($test);
                $resultTest = $test->runTest();
                if (!empty($resultTest)) {
                    $results[$fileName][$test->name] = $resultTest;
                }
            }
        }

        if (empty($results)) {
            return [
                "status" => true,
                "message" => "All tests passed successfully"
            ];
        }

        return [
            "status" => false,
            "message" => "Some tests failed",
            "results" => $results
        ];
    }

    private function getJsonFile(string $name): array
    {
        return json_decode(file_get_contents($this->dirTests . "/" . $name), true);
    }
}


print new Index();
