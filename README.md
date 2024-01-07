# HttpPromise

A classe `HttpPromise` oferece uma maneira assíncrona de realizar requisições HTTP usando cURL, retornando Promises para operações assíncronas.

## Instalação

Para utilizar esta classe, você precisa integrá-la ao seu projeto PHP. Você pode fazer isso incluindo o arquivo `HttpPromise.php` em seu projeto ou usando um sistema de autoload.

## Requisitos

- PHP 8.0 ou superior
- Extensão cURL habilitada

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
