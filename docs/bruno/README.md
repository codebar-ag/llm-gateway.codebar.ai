# Bruno collection

A [Bruno](https://www.usebruno.com) collection covering every LLM Gateway
endpoint (see the main [README](../../README.md#endpoints) for the full
behaviour table).

## Use

1. Open this folder (`docs/bruno`) as a collection in Bruno.
2. Select the **local** environment and set `host` and `key` to match your
   setup — `key` is whatever `Authorization: Bearer …` the upstream expects;
   it is replayed unchanged and never validated by the gateway itself.
   Set `gateway` to a gateway id from `/gateways` if you want to use
   **Queue on a specific gateway**; every other request goes to the default.
3. Run **Create response** first. It stores the returned `id` into the
   `responseId` environment variable, which **Show/Cancel/Delete response**
   reuse — an entry is only readable with the same key that queued it, so
   stay on one environment per key.

`Queue chat completion`, `Create response` and `Queue on a specific gateway`
all go through
`throttle:queue` (`BACKLOG_RATE_LIMIT`, default 60/min per key). Reading is
never throttled.
