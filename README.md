# rezeptoria
## recipe database

## Tests

```bash
composer test          # the whole suite
php bin/phpunit tests/Unit
```

The suite runs against SQLite (`var/test.db`, configured in `.env.test`), so it
needs neither a database server nor any credentials. Each test rebuilds the
schema from the mapping, which means the MySQL migrations are *not* exercised
here — run `doctrine:migrations:migrate` against a real database to check those.

Form CSRF protection is switched off in the test environment: the stateless
token is completed by JavaScript in the browser, which BrowserKit does not run.

## Advertisement slots

Ads are rendered through a single reusable partial:
`templates/partials/_ad.html.twig`.

The slot only renders when real ad markup is passed via the `code` param.
Without it nothing is output, so empty placeholders never take up space or
show a fallback box.

### Parameters

| Param    | Default       | Description                                                              |
|----------|---------------|--------------------------------------------------------------------------|
| `code`   | `''`          | Ad-network markup to embed. When empty the slot is hidden entirely.      |
| `format` | `'rectangle'` | Size preset: `leaderboard`, `rectangle`, `skyscraper`, or `infeed`.      |
| `label`  | `'Anzeige'`   | Small disclosure label shown in the slot corner.                         |

### Usage

Placeholder (renders nothing until `code` is supplied):

```twig
{{ include('partials/_ad.html.twig', { format: 'leaderboard' }) }}
```

Active slot with a real ad (e.g. Google AdSense):

```twig
{{ include('partials/_ad.html.twig', {
    format: 'leaderboard',
    code: '<ins class="adsbygoogle" style="display:block"
                data-ad-client="ca-pub-XXXX"
                data-ad-slot="1234567890"
                data-ad-format="auto"></ins>'
}) }}
```

> **Note:** `code` is output with `|raw`, so only pass trusted ad-network
> markup — never user-supplied content.

Existing slots live in `base.html.twig`, `recipe/index.html.twig`, and
`recipe/show.html.twig`.
