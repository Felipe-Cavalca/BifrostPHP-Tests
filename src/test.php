<?php

include_once 'config.php';

class Test
{
    private array $setup = [];
    private Config $config;
    private array $requiredFields = [
        "urlBase",
        "name",
        "description",
        "endpoint",
        "method",
        "headers"
    ];

    public function __construct(array $setup)
    {
        $this->config = Config::getInstance();
        $this->setup = array_merge($this->config->settings, $setup);
    }

    public function __get($name)
    {
        switch ($name) {
            case "setup":
                return $this->setup;
            default:
                return $this->setup[$name] ?? null;
        }
    }

    public function runTest(): array
    {
        $result = [];

        $validate = $this->validate();
        if ($validate === true) {
            $result = $this->curl();
        } else {
            $result["status"] = false;
            $result["message"] = $validate;
        }

        return $result;
    }

    private function validate(): bool|string
    {
        foreach ($this->requiredFields as $field) {
            if (!array_key_exists($field, $this->setup)) {
                return "Field $field is required";
            }
        }

        return true;
    }

    private function curl(): array
    {
        $ch = curl_init();
        $headers = [];
        $url = $this->urlBase . $this->endpoint;

        if ($this->query !== null) {
            $url .= "?" . http_build_query($this->query);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$headers) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) {
                return $len;
            }
            $headers[strtolower(trim($header[0]))] = trim($header[1]);
            return $len;
        });

        // Adiciona os dados no corpo da requisição
        if ($this->body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        // Separar cabeçalhos do corpo da resposta
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);

        return [
            "httpCode" => $httpCode,
            "headers" => $headers,
            "response" => $body,
            "totalTime" => $totalTime
        ];
    }
}
