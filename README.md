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

## Recipe images

A recipe carries images in three places, all stored as plain file names under
`public/uploads/images`:

| Where           | Held by                    | Shown as                                                   |
|-----------------|----------------------------|------------------------------------------------------------|
| Teaser          | `Recipe.teaserImage`       | Title image, listing thumbnail and `og:image` of the page. |
| Gallery         | `RecipeImage` (0–12 rows)  | Slider under the title image; a click opens the lightbox.  |
| Section picture | `RecipeSection.image`      | One picture inside the section it belongs to.              |

All three go through `ImageUploadType`, so they are judged by the same rules —
JPG, PNG or WebP, at most 2 MB, content checked rather than the extension.

`RecipeImageUpdater` is what connects the form to the files: it writes the
uploads to disk, hands the recipe the file names, and deletes what the recipe
stopped pointing at. An upload that cannot be written leaves the previous image
in place instead of replacing it with a broken reference, and a gallery row
that was added but never given a file is dropped rather than saved.

A gallery image is removed by removing its row. The teaser and the section
pictures carry a "remove" box instead, shown only once there is an image to
take away. Picking a new file wins over a ticked box: the file says more
clearly what the image should be.

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
