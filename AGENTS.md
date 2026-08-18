# Repository Agent Instructions

## Architecture and code quality

- Keep code, comments, and public documentation in English.
- Preserve the provider boundary: application code depends on typed WaveSpeed contracts and values, never on raw response arrays.
- Treat model schemas and prices as live provider data. The bundled catalog is an offline snapshot, not billing authority.
- Never retry generation submission automatically: a disconnected POST may already have created a billable prediction.
- Never log API keys, webhook secrets, prompts, uploaded media, or generated output URLs by default.
- Document intent, state boundaries, security assumptions, and non-obvious tradeoffs in new or significantly changed code.
- Add scenario-oriented comments to tests.
- Run `composer validate --strict` and `composer check` before publishing.

## Publication boundary

- Do not publish, push, tag, or create Packagist packages unless the user explicitly asks for publication.
- Before any live generation, estimate the exact input price and keep smoke tests at the cheapest practical settings.
