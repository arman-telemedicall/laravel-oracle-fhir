# Contributing

## Local setup
- `composer install`
- `composer test`
- `composer format`
- `composer format-test`
- `composer analyse`

## Pull requests
- Keep changes focused and well-tested.
- Ensure the following pass before requesting review:
  - `composer test`
  - `composer format-test`
  - `composer analyse`

## Security
Do not commit secrets. Use environment variables via the package config.
