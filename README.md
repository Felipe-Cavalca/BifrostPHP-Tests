# BifrostPHP - Módulo de Testes

Este projeto fornece um pequeno framework em PHP para realizar testes de integração de APIs HTTP. Os cenários de testes são descritos em arquivos JSON e executados de forma automatizada dentro de um contêiner Docker.

## Requisitos

- Docker e Docker Compose instalados.

## Configuração Inicial

Crie um arquivo `.setupTests.json` no diretório de testes com as configurações padrão que serão aplicadas a todos os cenários:

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

## Estrutura de um Cenário de Teste

Cada arquivo JSON dentro do diretório `/tests` contém um array com definições de testes. Os campos principais de cada teste são:

- `name` e `description`: identificação do teste.
- `endpoint` e `method`: caminho e método HTTP da requisição.
- `headers`, `query` e `body`: informações enviadas na requisição.
- `tests`: regras de validação da resposta.
- `store_response`: mapa de valores da resposta que serão reutilizados em testes seguintes.

### Tipos de Validação

Dentro da chave `tests` você pode utilizar diversas regras:

- `status_code`: código HTTP exato esperado.
- `status_code_in`: lista de códigos possíveis.
- `status_code_in_range`: intervalo de códigos permitidos.
- `headers`: verifica o valor de cabeçalhos específicos.
- `headers_contains`: garante que determinados cabeçalhos existam.
- `body`: compara o corpo da resposta com o JSON informado.
- `body_contains`: checa se campos específicos estão presentes.
- `body_contains_value`: valida valores de campos do corpo.
- `json_schema`: estrutura esperada para o corpo em formato JSON Schema.
- `response_time_max`: tempo máximo (segundos) para a resposta.

## Reutilização de Dados

Valores armazenados em `store_response` podem ser referenciados em outros cenários usando o formato `{{nome_da_variavel}}` em qualquer campo do teste.

## Executando os Testes

1. Coloque seus arquivos de cenário dentro do diretório `tests` (o repositório inclui `tests-demo` apenas como exemplo).
2. Execute `docker-compose up -d` para iniciar o contêiner.
3. Acesse `http://localhost:81` para ver o resultado em formato JSON. Caso todos os testes passem, uma mensagem de sucesso será exibida.

### Exemplo Simplificado

```json
[
    {
        "name": "Login com ADM",
        "description": "Loga com ADM",
        "endpoint": "/auth/login",
        "method": "POST",
        "body": {
            "email": "admin@dossier.com",
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
                    "data": {
                        "type": "object",
                        "properties": {
                            "id": { "type": "string", "required": true }
                        }
                    }
                }
            }
        }
    },
    {
        "name": "Lista usuário",
        "description": "Consulta dados do usuário logado",
        "endpoint": "/user/{{user_id}}",
        "method": "GET",
        "tests": {
            "status_code": 200
        }
    }
]
```

Este módulo facilita a automação de testes de APIs REST, permitindo criar cenários complexos de maneira simples e reaproveitar valores entre eles.
