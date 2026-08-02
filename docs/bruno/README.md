# Bruno collection

A [Bruno](https://www.usebruno.com) collection covering every LLM Gateway
endpoint (see the main [README](../../README.md#endpoints) for the full
behaviour table).

## Two ways in

**Open the folder.** In Bruno, *Open Collection* → pick `docs/bruno`. Nothing is
imported or copied: Bruno reads the `.bru` files in place, so edits are diffable
and land in git like any other change. This is the version with the request
chaining — **Create response** stores the returned id into `{{responseId}}` for
**Show/Cancel/Delete** to reuse.

**Import a single file.** *Import Collection* → *File* → pick
[`docs/openapi.yaml`](../openapi.yaml). One self-contained OpenAPI 3.0 document
with the full documentation — passthrough, ownership, statuses, the `queue`
block, every response shape and every error type. Bruno turns each operation
into a request and each `description` into that request's docs pane.

Take the import route if you want the whole API in one file, or to hand it to
someone who is not working in this repo. Take the folder route if you want the
chaining and the environments below. The same file imports into Postman,
Insomnia or Swagger UI, since it is a plain OpenAPI spec rather than a
Bruno-specific export.

## Environments

Only the folder collection carries these; an import brings its base URL from the
spec's `servers` block instead.

| Environment | `host` |
|---|---|
| `local` | the Herd site, `https://llm-gateway.codebar.test` |
| `public` | the hostname the Cloudflare Tunnel publishes — **set this yourself** |

`public` ships with a placeholder (`https://llm.example.com`). Point it at your
own tunnel hostname before the first run; nothing else differs between the two.

Set `key` to whatever `Authorization: Bearer …` the upstream expects — it is
replayed unchanged and never validated by the gateway itself. Set `gateway` to a
gateway id from `/gateways` if you want **Queue on a specific gateway**; every
other request goes to the default.

An entry is only readable with the same key that queued it, so stay on one
environment per key — switching mid-flow turns **Show response** into a `404`.

## Order

Every request that costs the upstream compute is posted first and collected
second: **Create response** → **Show response**, **Create embedding** →
**Collect embedding**, **Create moderation** → **Collect moderation**. Each
create stores the new id in the environment, so the collect beside it needs
no copy-paste.

`Queue chat completion`, `Create response`, `Queue on a specific gateway`,
`Create embedding` and `Create moderation` all queue, and all go through
`throttle:queue` (`BACKLOG_RATE_LIMIT`, default 60/min per key). Reading —
collecting an answer, `List models`, `Show model` — is never throttled,
because polling is how an answer is meant to be collected.

## Through the tunnel

The tunnel publishes the API and the dashboard as a default deny, so a request
that works locally can still `404` publicly — that is the ingress rules, not the
app. Reachable paths are `/up`, `/v1/…`, `/{gateway}/v1/…` for the API, and
`/`, `/entries/…`, `/gateways…`, `/build/…`, `/favicon.*` for the dashboard.
Everything else is refused before it reaches the origin. See
[Public access](../../README.md#public-access).
