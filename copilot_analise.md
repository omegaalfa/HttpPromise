Análise Comparativa: AsyncClient vs. HttpPromise
Este documento compara duas bibliotecas de cliente HTTP assíncrono em PHP: AsyncClient da Omegaalfa\AsyncFramework e HttpPromise da Omegaalfa\HttpPromise. Ambas as bibliotecas utilizam cURL e Promises para oferecer requisições assíncronas, mas com diferentes abordagens em termos de arquitetura, recursos e extensibilidade.
Comparativo Detalhado



Característica
AsyncClient (Omegaalfa\AsyncFramework)
HttpPromise (Omegaalfa\HttpPromise)




Gerenciamento de Loop
Utiliza FiberEventLoop (implícito ou explícito)
Utiliza EventLoop customizado com CurlMultiHandle


Gerenciamento de Handles
Gerencia um array de CurlHandle e seus Deferred associados.
Utiliza um ConnectionPool para reuso de CurlHandle.


API de Configuração
Métodos de setter (setTimeout, setConnectTimeout, etc.).
API fluente (withBaseUrl, withBearerToken, asJson, etc.)


Validação de URL
Validação básica com filter_var.
Validação robusta com UrlValidator para prevenir SSRF.


Middleware
Não possui um sistema de middleware explícito.
Suporta pipeline de middlewares customizáveis.


Retentativas (Retries)
Não implementado nativamente.
Implementado com lógica de backoff exponencial (RetryHandler).


Métricas
Não implementado nativamente.
Implementado com rastreamento detalhado (Metrics).


Tratamento de Erros
Exceções AsyncException e JsonException.
Exceções HttpException, InvalidArgumentException, RuntimeException.


Formato de Resposta
Array associativo com status, reason, headers, body, e infos do cURL.
Implementa Psr\Http\Message\ResponseInterface (via Laminas\Diactoros).


Reuso de Conexão
Não explícito.
Implementado via ConnectionPool para otimizar performance.


HTTP/2
Tenta usar CURL_HTTP_VERSION_2_0 se HTTPS.
Suporte explícito e configurável (withHttp2).


Keep-Alive
Configurações básicas (CURLOPT_TCP_KEEPALIVE).
Configurações explícitas e configuráveis (withTcpKeepAlive).


Tratamento de Body
Processa arrays e strings.
Mais flexível, com suporte a JSON, form data e CURLFile.


Interface de Promisse
Utiliza uma implementação customizada (Promise, Deferred).
Utiliza uma implementação mais completa (PromiseInterface, Promise, Deferred).


Estilo de Código
Mais procedural, com métodos de configuração diretos.
Mais orientado a objetos e declarativo, com API fluente e imutabilidade.



Considerações de Performance
Em termos de performance bruta, ambas as bibliotecas compartilham a mesma base tecnológica (cURL multi handle), o que significa que em cenários de requisições simples e sem muita sobrecarga de configuração, a diferença de performance tende a ser marginal. No entanto, a biblioteca HttpPromise apresenta características que podem levar a uma performance superior em cenários mais complexos e de larga escala:


Connection Pool (ConnectionPool): O reuso de handles cURL é um fator crucial para a performance em cenários com muitas requisições paralelas. Ao manter conexões abertas e prontas para serem reutilizadas, a HttpPromise evita o overhead de inicializar e fechar handles cURL repetidamente. Isso pode resultar em uma redução significativa do tempo de conexão e da latência geral.


Otimizações de cURL Multi Handle: A HttpPromise demonstra um cuidado maior na configuração do CurlMultiHandle, incluindo o uso de CURLMOPT_PIPELINING com CURLPIPE_MULTIPLEX (quando suportado) e limites de conexão (CURLMOPT_MAX_TOTAL_CONNECTIONS, CURLMOPT_MAX_HOST_CONNECTIONS). Essas configurações visam otimizar o paralelismo e a utilização da rede.


Gerenciamento do Event Loop: A implementação do EventLoop na HttpPromise, combinada com o sistema de fila (queued) e processamento (pending), parece mais refinada para lidar com a concorrência e garantir que os recursos (slots de requisição) sejam utilizados eficientemente.


Robustez da API: Embora não seja diretamente uma métrica de performance, uma API mais organizada e com validações mais rigorosas (como a UrlValidator contra SSRF) pode prevenir erros e comportamentos inesperados que, em última instância, impactariam a performance ao forçar retentativas ou depuração.


Em resumo, espera-se que a HttpPromise tenha uma performance superior em cenários de alto volume e concorrência devido ao seu sistema de ConnectionPool e configurações mais agressivas de otimização do cURL.

Manual de Uso e Considerações de Performance
Introdução
Este guia compara as bibliotecas AsyncClient da Omegaalfa\AsyncFramework e HttpPromise da Omegaalfa\HttpPromise. Ambas oferecem um cliente HTTP assíncrono em PHP utilizando a extensão cURL e Promises. Analisaremos suas funcionalidades, arquiteturas e, crucialmente, suas implicações na performance.
Comparativo Detalhado



Característica
AsyncClient (Omegaalfa\AsyncFramework)
HttpPromise (Omegaalfa\HttpPromise)




Gerenciamento de Loop
Utiliza FiberEventLoop (implícito ou explícito).
Utiliza EventLoop customizado com CurlMultiHandle.


Gerenciamento de Handles
Gerencia um array de CurlHandle e seus Deferred associados.
Utiliza um ConnectionPool para reuso de CurlHandle.


API de Configuração
Métodos de setter (setTimeout, setConnectTimeout, etc.).
API fluente (withBaseUrl, withBearerToken, asJson, etc.)


Validação de URL
Validação básica com filter_var.
Validação robusta com UrlValidator para prevenir SSRF.


Middleware
Não possui um sistema de middleware explícito.
Suporta pipeline de middlewares customizáveis.


Retentativas (Retries)
Não implementado nativamente.
Implementado com lógica de backoff exponencial (RetryHandler).


Métricas
Não implementado nativamente.
Implementado com rastreamento detalhado (Metrics).


Formato de Resposta
Array associativo com status, reason, headers, body, e infos do cURL.
Implementa Psr\Http\Message\ResponseInterface (via Laminas\Diactoros).


Reuso de Conexão
Não explícito.
Implementado via ConnectionPool para otimizar performance.


HTTP/2
Tenta usar CURL_HTTP_VERSION_2_0 se HTTPS.
Suporte explícito e configurável (withHttp2).


Keep-Alive
Configurações básicas (CURLOPT_TCP_KEEPALIVE).
Configurações explícitas e configuráveis (withTcpKeepAlive).


Tratamento de Body
Processa arrays e strings.
Mais flexível, com suporte a JSON, form data e CURLFile.


Interface de Promisse
Utiliza uma implementação customizada (Promise, Deferred).
Utiliza uma implementação mais completa (PromiseInterface, Promise, Deferred).


Estilo de Código
Mais procedural, com métodos de configuração diretos.
Mais orientado a objetos e declarativo, com API fluente e imutabilidade.



Análise de Performance
Ambas as bibliotecas se baseiam na poderosa funcionalidade de múltiplas requisições do cURL (curl_multi_*), o que as torna intrinsecamente eficientes para processamento assíncrono. No entanto, a HttpPromise apresenta diversas características que a colocam em vantagem em cenários de alta performance:


Connection Pooling: A característica mais significativa para performance na HttpPromise é o ConnectionPool. Ao gerenciar um conjunto de handles cURL abertos e prontos para serem reutilizados por host, ela minimiza drasticamente o tempo de estabelecimento de novas conexões. Isso é um ganho substancial em aplicações que realizam um grande número de requisições para o mesmo servidor ou para múltiplos servidores. O AsyncClient não possui um mecanismo explícito de reuso de conexão, o que implica em custos maiores para cada nova requisição.


Otimizações do cURL Multi: A HttpPromise aplica configurações mais detalhadas e otimizadas ao CurlMultiHandle, como CURLMOPT_PIPELINING para multiplexação de requisições e limites explícitos de conexões (CURLMOPT_MAX_TOTAL_CONNECTIONS, CURLMOPT_MAX_HOST_CONNECTIONS). Essas otimizações visam maximizar a utilização da rede e a concorrência.


Gerenciamento do Event Loop e Filas: A HttpPromise possui um sistema de EventLoop com filas explícitas (queued e pending) e controle de concorrência (maxConcurrent). Isso permite um gerenciamento mais granular e eficiente da carga de trabalho, garantindo que as requisições sejam processadas de forma otimizada sem sobrecarregar os recursos. O AsyncClient também gerencia requisições pendentes, mas a HttpPromise parece ter um controle mais refinado sobre o fluxo.


API Fluente e Imutabilidade: Embora seja uma questão de estilo, a API fluente e imutável da HttpPromise facilita a configuração e a composição de requisições. Essa abordagem, combinada com validações mais rigorosas (como a UrlValidator contra SSRF), pode prevenir erros comuns que, em última instância, impactariam a performance ao causar falhas ou retentativas desnecessárias.


Suporte a HTTP/2 e Keep-Alive: O suporte explícito e configurável para HTTP/2 (withHttp2) e TCP Keep-Alive (withTcpKeepAlive) na HttpPromise permite aproveitar melhor as eficiências desses protocolos em conexões de longa duração e baixa latência.


Conclusão de Performance:
Considerando o ConnectionPool, as otimizações mais refinadas do CurlMultiHandle e a arquitetura geral focada em eficiência para cenários de alto volume, a HttpPromise tende a apresentar um desempenho superior ao AsyncClient, especialmente em aplicações que demandam um grande número de requisições assíncronas simultâneas ou sequenciais para os mesmos hosts. O AsyncClient é funcional e direto, mas carece de alguns dos mecanismos de otimização de baixo nível que a HttpPromise implementa.
Recomendações

Para performance máxima em larga escala e gerenciamento avançado de conexões: Utilize a HttpPromise.
Para uma implementação mais simples e direta, sem a necessidade de pooling de conexão explícito ou recursos avançados como retries e middlewares: O AsyncClient pode ser suficiente, desde que os custos de inicialização de novas conexões por requisição sejam aceitáveis.

Ao escolher, considere a complexidade da sua aplicação, o volume de requisições esperadas e a necessidade de recursos como retentativas, validação de segurança robusta e customização de middlewares.
