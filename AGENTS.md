# scout — Agent Instructions

## Style

Respond terse like smart caveman. All technical substance stay. Only fluff die.
Drop: articles, filler (just/really/basically/actually), pleasantries, hedging. Fragments OK.
Code blocks unchanged. Technical terms exact.

---

## Project

Stateless PHP/Symfony HTTP service. One endpoint (`POST /route`) returns traffic-aware distance and duration between an ordered list of points, from the Google Routes API. Plus `GET /healthz`.

PHP 8.4, Symfony 7.3, FrankenPHP, Docker.

---

## File map

```
public/index.php                                # entry; EnvGuard::assert() before Kernel boot
src/Kernel.php                                   # micro-kernel
src/Config/EnvGuard.php                          # required-env validation, fail-fast wrapper
src/Http/TokenListener.php                       # bearer-token gate on kernel.request
src/Controller/HealthController.php              # GET /healthz
src/Controller/RouteController.php               # POST /route: validate, call provider, map errors
src/Provider/RouteProvider.php                   # provider interface
src/Provider/RouteResult.php                     # immutable result value object
src/Provider/Exception/NoRouteException.php      # no route found
src/Provider/Exception/ProviderException.php     # transport/quota/provider failure
src/Provider/Google/GoogleRoutesProvider.php     # all Google-specific request/response handling
src/Log/ProbeFilterHandler.php                   # Monolog handler wrapper, drops /healthz probe lines
config/services.yaml                             # DI wiring, RouteProvider → GoogleRoutesProvider alias
config/packages/monolog.yaml                     # main handler = the probe filter, not a plain stream
```

No import cycles. `RouteController` and `RouteProvider` depend on nothing Google-specific.

---

## Key invariants

- The controller must never import from `App\Provider\Google`. Check: `grep -r "Provider\\\\Google" src/Controller/` must return nothing.
- Provider error text never reaches an HTTP response body; it goes to the log only (`ProviderException`/`NoRouteException` messages may contain provider detail — the controller maps them to fixed generic messages).
- Env prefix `SCOUT_` for our own variables. App refuses to start without `SCOUT_API_TOKEN` and `GOOGLE_ROUTES_API_KEY` (`EnvGuard`).
- No persistence, no cache, no scheduling, no message formatting — those belong to other services.
- Probe traffic must not reach the log. `GET /healthz` runs every ten seconds per replica; its
  `request.INFO` lines are filtered by `ProbeFilterHandler`. Warning and above always pass, probe
  path or not.
- No git write operations in this project unless explicitly asked.

---

## TDD workflow

Write failing test → verify FAIL → write implementation → verify PASS.
Run tests: `vendor/bin/phpunit`

---

## Adding a provider

Implement `App\Provider\RouteProvider` (`computeRoute(array $points): RouteResult`, `name(): string`). Wire it in `config/services.yaml` by changing the one alias line:

```yaml
App\Provider\RouteProvider: '@App\Provider\YourNamespace\YourProvider'
```

Nothing else changes.

---

## Do not

- Add runtime deps beyond `symfony/framework-bundle`, `symfony/http-client`, `symfony/monolog-bundle`, `symfony/runtime`, `symfony/dotenv`. No Doctrine, no ORM, no messenger, no security-bundle, no twig, no serializer, no validator.
- Let Google's raw payload or raw error text reach an HTTP response body.
- Run `git commit` or any git write unless explicitly asked.
