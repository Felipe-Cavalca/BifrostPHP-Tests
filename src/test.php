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
        $this->setup = $this->replacePlaceholders($this->array_merge_recursive_distinct($this->config->settings, $setup));
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
            $response = $this->curl();
            $result = $this->validateResponse($response);
            $this->saveParams($response);
        } else {
            $result = [
                "name" => "Validation",
                "status" => false,
                "message" => $validate
            ];
        }

        $result = array_filter($result, function ($item) {
            return isset($item['status']) && $item['status'] === false;
        });

        $result = array_column($result, null, 'name');

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
            "response" => json_decode($body, true),
            "totalTime" => $totalTime
        ];
    }

    private function validateResponse(array $response): array|null
    {
        $resultTests = [];

        if ($this->tests == null) {
            return [];
        }

        if (isset($this->tests["status_code"])) {
            $resultTests[] = [
                "name" => "Status Code",
                "status" => $response["httpCode"] == $this->tests["status_code"],
                "expected" => $this->tests["status_code"],
                "received" => $response["httpCode"]
            ];
        }

        if (isset($this->tests["status_code_in"])) {
            $resultTests[] = [
                "name" => "Status Code In",
                "status" => in_array($response["httpCode"], $this->tests["status_code_in"]),
                "expected" => $this->tests["status_code_in"],
                "received" => $response["httpCode"]
            ];
        }

        if (isset($this->tests["status_code_in_range"])) {
            $resultTests[] = [
                "name" => "Status Code In Range",
                "status" => $response["httpCode"] >= $this->tests["status_code_in_range"][0] && $response["httpCode"] <= $this->tests["status_code_in_range"][1],
                "expected" => $this->tests["status_code_in_range"],
                "received" => $response["httpCode"]
            ];
        }

        if (isset($this->tests["headers"])) {
            foreach ($this->tests["headers"] as $key => $value) {
                if (isset($response["headers"][$key]) == false) {
                    $resultTests[] = [
                        "name" => "Header " . $key,
                        "status" => false,
                        "expected" => $value,
                        "received" => null
                    ];
                    continue;
                }
                $resultTests[] = [
                    "name" => "Header " . $key,
                    "status" => $response["headers"][$key] == $value,
                    "expected" => $value,
                    "received" => $response["headers"][$key]
                ];
            }
        }

        if (isset($this->tests["headers_contains"])) {
            $headersReceived = array_keys($response["headers"]);
            foreach ($this->tests["headers_contains"] as $value) {
                $resultTests[] = [
                    "name" => "Header Contains " . $value,
                    "status" => in_array($value, $headersReceived),
                    "expected" => $value
                ];
            }
        }

        if (isset($this->tests["body"])) {
            $resultTests[] = [
                "name" => "Body",
                "status" => $response["response"] == $this->tests["body"],
                "expected" => $this->tests["body"],
                "received" => $response["response"]
            ];
        }

        if (isset($this->tests["body_contains"])) {
            if (!isset($response["response"])) {
                $resultTests[] = [
                    "name" => "Body Contains",
                    "status" => false,
                    "expected" => $this->tests["body_contains"],
                    "received" => null
                ];
            }
            foreach ($this->tests["body_contains"] as $value) {
                $resultTests[] = [
                    "name" => "Body Contains " . $value,
                    "status" => isset($response["response"][$value]),
                    "expected" => $value
                ];
            }
        }

        if (isset($this->tests["body_contains_value"])) {
            if (!isset($response["response"])) {
                $resultTests[] = [
                    "name" => "Body Contains Value",
                    "status" => false,
                    "expected" => $this->tests["body_contains_value"],
                    "received" => null
                ];
            }
            foreach ($this->tests["body_contains_value"] as $key => $value) {
                $resultTests[] = [
                    "name" => "Body Contains Value " . (is_array($value) ? json_encode($value) : $value) . " in " . $key,
                    "status" => isset($response["response"][$key]) && $response["response"][$key] == $value,
                    "expected" => $value,
                    "received" => $response["response"][$key] ?? null
                ];
            }
        }

        if (isset($this->tests["response_time_max"])) {
            $resultTests[] = [
                "name" => "Response Time Max",
                "status" => $response["totalTime"] <= $this->tests["response_time_max"],
                "expected" => $this->tests["response_time_max"],
                "received" => $response["totalTime"]
            ];
        }

        if (isset($response["response"]) && isset($this->tests["json_schema"])) {
            $resultTests[] = [
                "name" => "Json Schema",
                "status" => $this->validateJsonSchema($response["response"], $this->tests["json_schema"]),
                "expected" => $this->tests["json_schema"],
                "received" => $response["response"]
            ];
        }

        return $resultTests;
    }

    private function validateJsonSchema(mixed $data, array $schema): bool
    {
        if (isset($schema['type'])) {
            $type = $schema['type'];
            if ($type === 'object') {
                if (!is_array($data)) {
                    return false;
                }
                if (isset($schema['properties'])) {
                    foreach ($schema['properties'] as $key => $propertySchema) {
                        if (isset($propertySchema['required']) && $propertySchema['required'] && !isset($data[$key])) {
                            return false;
                        }
                        if (isset($data[$key]) && !$this->validateJsonSchema($data[$key], $propertySchema)) {
                            return false;
                        }
                    }
                }
            } elseif ($type === 'array') {
                if (!is_array($data)) {
                    return false;
                }
                if (isset($schema['items'])) {
                    foreach ($data as $item) {
                        if (!$this->validateJsonSchema($item, $schema['items'])) {
                            return false;
                        }
                    }
                }
            } elseif ($type === 'string') {
                if (!is_string($data) || (isset($schema['pattern']) && !preg_match("/" . $schema['pattern'] . "/", $data))) {
                    return false;
                }
            } elseif ($type === 'integer') {
                if (!is_int($data)) {
                    return false;
                }
            } elseif ($type === 'number') {
                if (!is_numeric($data)) {
                    return false;
                }
            } elseif ($type === 'boolean') {
                if (!is_bool($data)) {
                    return false;
                }
            }
        }
        return true;
    }

    private function array_merge_recursive_distinct(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->array_merge_recursive_distinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    private function getNestedValue(array $array, string $path)
    {
        $keys = explode('.', $path); // Quebra o caminho "data.id" em ["data", "id"]
        foreach ($keys as $key) {
            if (!isset($array[$key])) {
                return null; // Retorna null se o caminho não existir
            }
            $array = $array[$key];
        }
        return $array;
    }

    private function saveParams(array $result): void
    {
        if (isset($this->setup["store_response"])) {
            foreach ($this->setup["store_response"] as $key => $store) {
                $this->config->saveParam($key, $this->getNestedValue($result, $store));
            }
        }
    }

    private function replacePlaceholders(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Substitui todas as ocorrências de {{variavel}}
                $data[$key] = preg_replace_callback('/\{\{(.+?)\}\}/', function ($matches) {
                    $variableName = $matches[1];
                    return $this->config->getParam($variableName) ?? $matches[0]; // Mantém original se não existir
                }, $value);
            } elseif (is_array($value)) {
                $data[$key] = $this->replacePlaceholders($value);
            }
        }
        return $data;
    }
}
