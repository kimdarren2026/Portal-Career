# infra/env — Environment Configuration Templates

**Status: reserved and empty.**

This directory will hold `.env.example` — a template listing every required environment variable
with **placeholder** values and a short comment explaining each one.

It has deliberately not been created yet: no application code exists, so no environment variable
has been defined. Inventing variables now would guess at a stack that has not been chosen.

## Rules

- `.env.example` is committed. It contains placeholders only, never real values.
- Real `.env` files are never committed; they are excluded by the root `.gitignore`.
- Never store a real SMTP password, API key, database password, or private token here.
