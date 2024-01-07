# HttpPromise

A classe `HttpPromise` oferece uma maneira assíncrona de realizar requisições HTTP usando cURL, retornando Promises para operações assíncronas.

## Instalação
```bash
composer require omegaalfa/http-promise
```

## Requisitos

- PHP 8.0 ou superior
- Extensão cURL habilitada

## Métodos Disponíveis

A classe `HttpPromise` oferece os seguintes métodos:

### `get($url, $headers = [])`

Realiza uma requisição GET assíncrona.

#### Parâmetros:

- `$url` (string): URL para a requisição.
- `$headers` (array): Cabeçalhos opcionais para a requisição.

### `post($url, $params = null, $headers = [])`

Realiza uma requisição POST assíncrona.

#### Parâmetros:

- `$url` (string): URL para a requisição.
- `$params` (mixed|null): Parâmetros para a requisição (opcional).
- `$headers` (array): Cabeçalhos opcionais para a requisição.

### `put($url, $params = null, $headers = [])`

Realiza uma requisição PUT assíncrona.

#### Parâmetros:

- `$url` (string): URL para a requisição.
- `$params` (mixed|null): Parâmetros para a requisição (opcional).
- `$headers` (array): Cabeçalhos opcionais para a requisição.

### `delete($url, $params = null, $headers = [])`

Realiza uma requisição DELETE assíncrona.

#### Parâmetros:

- `$url` (string): URL para a requisição.
- `$params` (mixed|null): Parâmetros para a requisição (opcional).
- `$headers` (array): Cabeçalhos opcionais para a requisição.

Estes são alguns dos principais métodos oferecidos pela classe `HttpPromise` para facilitar o envio de requisições HTTP assíncronas.

## Uso

### Exemplo de utilização:

```php
use src\http\HttpPromise;

$http = new HttpPromise();

// Realizar uma requisição GET
$http->get('https://api.exemplo.com')
    ->then(
        function ($response) {
            // Lida com a resposta da requisição
            echo $response->getBody()->getContents();
        },
        function ($error) {
            // Lida com erros na requisição
            echo 'Erro na requisição: ' . $error;
        }
    );
```

## Contribuição

Se desejar contribuir com melhorias ou correções, fique à vontade para criar uma pull request ou abrir uma issue no repositório.

## Licença

Este projeto está licenciado sob a Licença MIT.
