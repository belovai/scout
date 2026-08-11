# scout

Stateless traffic-aware route distance and duration service. One endpoint, one job: given an ordered list of points, return the current traffic-aware driving distance and duration between them. Comparison, formatting, and business logic belong to the caller.

## `POST /route`

Requires `Authorization: Bearer <SCOUT_API_TOKEN>`.

Request body:

```json
{
    "points": ["Budapest, Szent György tér 1, 1014", "Budapest, Kossuth Lajos tér 1-3, 1055"]
}
```

Each point is either a free-form address string or a `"lat, lng"` coordinate string, passed through to the provider as-is (no geocoding on our side). At least 2 points are required; order matters.

200 response:

```json
{
    "distance_meters": 5111,
    "duration_seconds": 774,
    "provider": "google"
}
```

Status codes:

| Status | Body | Meaning |
|---|---|---|
| 200 | `{"distance_meters":int,"duration_seconds":int,"provider":string}` | Route computed |
| 400 | `{"error":"malformed json body"}` | Body is not valid JSON, or not a JSON object |
| 400 | `{"error":"points must be a list of strings"}` | `points` missing or not a list |
| 400 | `{"error":"points must be a list of non-empty strings"}` | An element is not a string, or is empty after trim |
| 400 | `{"error":"points must contain at least 2 elements"}` | Fewer than 2 points |
| 401 | `{"error":"unauthorized"}` | Missing or invalid bearer token |
| 422 | `{"error":"no route found"}` | Provider could not find a route for the given points |
| 502 | `{"error":"provider unavailable"}` | Provider transport/quota/server failure |

## `GET /healthz`

Returns `200 OK` with body `OK`. No authentication required.

## Configuration

| Variable | Required | Description |
|---|---|---|
| `SCOUT_API_TOKEN` | Yes | Bearer token required on every request except `/healthz` |
| `GOOGLE_ROUTES_API_KEY` | Yes | Google Routes API key |
| `APP_ENV` | No | `prod` (default) or `dev` |
| `APP_SECRET` | No | Symfony framework secret |

The app refuses to start without `SCOUT_API_TOKEN` and `GOOGLE_ROUTES_API_KEY`.

## Run with Docker

Pull the published image from GHCR:

```bash
docker pull ghcr.io/belovai/scout:latest
```

Run with `-e` flags:

```bash
docker run --rm -d --name scout -p 8080:8080 \
  -e SCOUT_API_TOKEN=change-me \
  -e GOOGLE_ROUTES_API_KEY=change-me \
  -e APP_SECRET=change-me \
  ghcr.io/belovai/scout:latest
```

Or with an env file (one `KEY=VALUE` per line, no `export`, no quotes needed):

```bash
docker run --rm -d --name scout -p 8080:8080 --env-file .env ghcr.io/belovai/scout:latest
```

`--env-file` only sets process environment variables inside the container — it does not mount or create a `.env` file on disk. Values passed this way (or via `-e`) always take precedence over the placeholder `.env` baked into the image.

Available tags: `latest` (default branch), `main`, `sha-<commit>`, and semver tags (`vX.Y.Z` pushes).

## Run locally

```bash
composer install
SCOUT_API_TOKEN=change-me GOOGLE_ROUTES_API_KEY=change-me php -S localhost:8000 -t public
```

## Tests

```bash
vendor/bin/phpunit
```

## Adding a provider

Implement `App\Provider\RouteProvider`, then change the single alias line in `config/services.yaml`:

```yaml
App\Provider\RouteProvider: '@App\Provider\Google\GoogleRoutesProvider'
```

Nothing else changes — the controller only ever depends on the interface.
