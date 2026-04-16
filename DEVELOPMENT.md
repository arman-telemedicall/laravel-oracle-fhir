# Development

## Goals
- Provide a Laravel integration package for Epic FHIR.
- Keep runtime behavior stable while maintaining a clean, testable internal architecture.

## Local setup
- `composer install`
- `composer test`
- `composer format`
- `composer format-test`
- `composer analyse`

## Develop without access to the main repo (Composer path repository)
In your application `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../path/to/laravel-epic-fhir",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "teleminergmbh/laravel-epic-fhir": "*"
  }
}
```

Then run:
- `composer update teleminergmbh/laravel-epic-fhir`

## Contracts policy
### Stable contract (outside the package)
The main application should depend on a stable interface you control (in the main app or a dedicated contracts repository). Changes must be reviewed and versioned.

### Internal contracts (inside the package)
Internal contracts inside `src/Contracts` are allowed for implementation speed. The main application must not depend on internal contracts until explicitly promoted.

## Acceptance criteria (freelancers)
- `composer test`
- `composer format-test`
- `composer analyse`
