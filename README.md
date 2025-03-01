# BifrostPHP - Test Module

Este repositório contém um framework de testes para requisições HTTP, permitindo a definição e execução de testes através de arquivos JSON de forma dinâmica e reutilizável.

## 🛠️ Configuração

1. Arquivo de Configuração: `.setupTests.json`

Este arquivo define configurações globais dos testes, como a URL base, cabeçalhos padrão e parâmetros de validação.Essas configurações serão aplicadas a todos os testes, mas cada teste pode sobrescrevê-las individualmente, conforme necessário.

Exemplo:
```json
{
    "urlBase": "http://api",
    "headers": {
        "Content-Type": "application/json"
    },
    "tests": {
        "status_code_in": [200, 201],
        "headers": {
            "content-type": "application/json; charset=utf-8"
        },
        "response_time_max": 0.5
    }
}
```

## 🔥 Estrutura dos Testes

Os testes são definidos em arquivos JSON e seguem um formato padronizado. Cada teste pode conter:

* Url base
* Nome e descrição
* Endpoint e método HTTP
* Cabeçalhos, query params e corpo da requisição
* Regras de validação
* Armazenamento de valores para reutilização em testes futuros

# 📌 Exemplo de Teste
```json
[
    {
        "name": "Login com ADM",
        "description": "Loga com ADM",
        "endpoint": "/auth/login",
        "method": "POST",
        "body": {
            "email": "test@email.com",
            "password": "123456"
        },
        "store_response": {
            "user_id": "response.data.id"
        },
        "tests": {
            "status_code": 200,
            "json_schema": {
                "type": "object",
                "properties": {
                    "statusCode": { "type": "integer", "required": true },
                    "isSuccess": { "type": "boolean", "required": true },
                    "data": {
                        "type": "object", "required": true,
                        "properties": {
                            "id": { "type": "string", "required": true },
                            "role": { "type": "string", "required": true }
                        }
                    }
                }
            },
            "response_time_max": 0.9
        }
    },
    {
        "name": "Lista um usuário",
        "description": "Lista um usuário apartir do id",
        "endpoint": "/user/{{user_id}}",
        "method": "GET",
        "tests": {
            "status_code": 200,
            "response_time_max": 0.9
        }
    }
]
```

## 🔄 Reutilização de Valores entre Testes

O framework permite armazenar valores da resposta de uma requisição e usá-los em testes futuros.

### Exemplo:

O teste de login armazena `user_id` da resposta.

O próximo teste usa `{{user_id}}` para acessar o perfil do usuário logado.

## 🚀 Execução dos Testes

Os testes são executados via HTTP. Basta acessar localhost:81 para obter um JSON com os resultados dos testes que falharam. Caso todos os testes sejam bem-sucedidos, a resposta conterá apenas uma mensagem de sucesso.

---

Este framework facilita a automação de testes de APIs RESTful, garantindo consistência e reutilização de dados de maneira eficiente.
