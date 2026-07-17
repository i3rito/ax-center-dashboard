# AX Center — Dashboard Centralizado de Produção

Laravel 7 + PHP 7.4. Consolida produção das plantas A e B (MySQL separados), com atualização em tempo real (Pusher), alerta de defeitos > 5% e insight via OpenAI.

## Requisitos

- PHP 7.4
- MySQL 8
- Composer
- Conta [Pusher Channels](https://pusher.com/channels) (free)
- Chave OpenAI (opcional, para o insight)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Crie os bancos `planta_a` e `planta_b` e ajuste `.env` (`DB_PLANTA_A_*`, `DB_PLANTA_B_*`, Pusher, `OPENAI_API_KEY`).

```bash
php artisan migrate --database=planta_a
php artisan migrate --database=planta_b
php artisan db:seed --database=planta_a
php artisan db:seed --database=planta_b
```

Suba o Apache apontando o document root para `public/` (ex.: `http://laravel7.test`) **ou**:

```bash
php artisan serve
```

Simulação em tempo real (insere registro em 01/02/2026 e dispara broadcast):

```bash
php artisan schedule:work
# ou manualmente:
php artisan production:simulate
```

## Demonstração

- URL: `/` — modos Planta A / Planta B / Consolidado
- Dia operacional simulado: **2026-02-01** (histórico: janeiro/2026)
- Alertas visuais quando a taxa de defeitos > 5%
- WebSocket: canal público `production`, evento `updated` → frontend refetch `/api/metrics`
- Insight: botão no dashboard → `POST /api/insights`

## Testes

```bash
php artisan test
# ou
vendor/bin/phpunit
```

## Decisões técnicas

- Planta A: agregação via **Eloquent**
- Planta B: agregação via **SQL puro** (`DB::select`)
- Eficiência: `(produzido - defeituosos) / produzido * 100`
- `QUEUE_CONNECTION=sync` + `ShouldBroadcastNow` (sem worker de fila)
