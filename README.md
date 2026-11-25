# Pxi Forge

An image generator built on the Pixelforge API for the PromptEdit take-home. Users write a prompt, see what it will cost, and watch it generate. They pay Pixelforge's cost plus 25%, from credits they buy through Stripe. Operators get a report of spend, provider cost and profit, reconciled against Pixelforge's own usage.

Built with Laravel 13, Inertia 3, Vue 3, Tailwind 4 and Postgres. Server code is organised by domain (`app/Domains`) as [Laravel Actions](https://laravelactions.com), with external APIs behind adapters in `app/Services`.

## Running it

Needs PHP 8.3+, Postgres, Composer and Bun.

```bash
composer setup          # install, .env, key, migrate, build
php artisan db:seed     # permissions, roles and demo users (password: "password")
composer dev            # app, queue worker and Vite
```

Fill these in `.env`:

| Variable | What for |
|---|---|
| `PIXELFORGE_API_KEY` | One key per environment, so each one's usage report matches its own records. |
| `PIXELFORGE_WEBHOOK_SECRET` | The signing secret for that key's webhook, which points at `/webhooks/imageGeneration/pixelforge`. |
| `STRIPE_SECRET` | A test-mode key (`sk_test_`). No real money moves. |
| `STRIPE_WEBHOOK_SECRET` | For `/webhooks/payment/stripe`, events `checkout.session.completed` and `checkout.session.async_payment_succeeded`. |
| `OBJECT_STORE_PROVIDER` | `s3` with the `AWS_*` settings, or `local` for the public disk in development. |

A queue worker must be running: every call to Pixelforge happens in a job. Without a public webhook URL (no tunnel), results still arrive through the fallback poll, just slower.

Tests run on fakes of Pixelforge, Stripe and the object store: `php artisan test`.

## How the money works

**Integer nanodollars everywhere.** Every amount is stored as a whole number of $0.000000001, in `bigint` columns. Pixelforge's rates are parsed from their decimal strings straight into integers, never through a float, so a generation's cost is exact. Rounding only happens when an amount is shown, and totals are summed from the exact values.

**Cost comes from tokens.** Pixelforge never sends a price. When a generation succeeds, we store its four token counts and the rates Pixelforge fixed when it accepted the job, then work out the cost from those (`Money::providerCost`). Later price changes don't touch old generations.

**The user pays cost × 1.25.** With today's rates this is always exact. If a future rate had more decimal places, the charge rounds up by less than a nanodollar. Failed generations cost the user nothing.

**The estimate is a range.** Before sending, the page shows the lowest and highest the image can cost. Reference images and output tokens are known exactly. Text tokens vary by ±15% and reasoning tokens by quality, so the range covers both ends, rounded outward so the real price always lands inside it.

**Timeouts are on us.** If Pixelforge hasn't finished after 5 minutes, we fail the generation and the user pays nothing. If it succeeds after that, the image still shows up for free, and we record what Pixelforge charged us so the report still matches.

## Duplicate and out-of-order events

Every change from Pixelforge, whether from the create response, a webhook or a poll, goes through one action, `ApplyProviderUpdate`. It locks the generation row, only lets the status move forward (queued, processing, then succeeded or failed), and writes money only on the single move to a final state. So:

- A webhook delivered twice is stored once, by its event id as primary key, and applied once.
- A stale `processing` event arriving after `succeeded` is ignored.
- A poll and a webhook reporting the same success can't both charge.
- A webhook that arrives before the create call returns still finds its row, through the generation id sent in `metadata`.

Webhook signatures are checked against the raw body with a constant-time compare, and events older than 5 minutes are rejected.

Sending is idempotent too. Each click carries a client id, unique in the database, so a double submit makes one generation. That generation's id is the `Idempotency-Key` sent to Pixelforge, so retrying a timed-out request never creates a second job.

## When Pixelforge pushes back

- **Rate limits and the 3-at-a-time concurrency limit** wait for `Retry-After` and try again, for up to 5 minutes.
- **Overloads** are retried up to 4 times with backoff, then fail free with a retry button.
- **Blocked prompts** fail free and close the chat, since resending the same prompt would only be blocked again.
- **The daily limit** fails free with a clear message.
- **Lost webhooks** are covered by a poll at 90 seconds, then every 30 seconds from 2 minutes, until the 5-minute limit.

The user always sees which of these happened, in plain words, and whether it cost anything.

## Credits

A balance is never stored. It's paid purchases minus what finished generations charged, so it can't drift from the records behind it.

Since the real price is only known after an image finishes, a running generation **holds** the top of its estimate. Sending checks that the user can cover that hold on top of anything already running, with the user's row locked so two tabs can't spend the same credits. The real price never exceeds the hold, so a balance can't go negative. When the image finishes, the hold turns into the actual charge, or into nothing if it failed.

Buying credits opens a Stripe Checkout in test mode (card `4242 4242 4242 4242`). The purchase is saved as unpaid first, and its id is Stripe's idempotency key. Both the return page and Stripe's webhook can mark it paid, in either order and any number of times, and it's credited once. The paid amount must match the pack exactly, so Checkout is pinned to USD.

## Spend report

`/reports` shows, for any range of up to 92 days, what users were charged, what Pixelforge charged us and the profit, per day and per user. Days are UTC and count each generation on the day it **completed**, which is how `GET /v1/usage` bills them. Each day sits beside Pixelforge's own figure for that day, with any difference flagged. Days where Pixelforge billed something we have no record of show up too.

## Access

Every action has its own permission (`generations.create`, `credits.purchase`, `reports.view` and so on), and roles are built from them in the UI. Members can generate and buy credits. The seeded Admin role can also see users, roles and the spend report. After adding permissions to the code, run `php artisan authorization:sync`.
