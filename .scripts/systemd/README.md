# Production process setup (one-time, manual)

Two persistent processes the Prospects pipeline needs that `deploy.sh` does not
manage: the queue worker (runs classify/draft-reply jobs) and the Reverb
WebSocket server (push-reactivity for the admin UI, replacing `->poll()`).
Neither exists today -- confirmed by reading `deploy.sh`, which only runs
`migrate`/`seed`/`config:cache`/etc. Without the queue worker, jobs just sit
in the `jobs` table forever, exactly like the local backlog that needed a
manual `queue:work` run before it processed anything.

## Install (done -- 2026-09-20)

Both units are installed at `/etc/systemd/system/` and `enabled` (confirmed
`User=ahmedia`, `mysqld.service` not `mysql.service` -- the initial guess
was wrong, fixed before installing). Deliberately **not started yet**:
production's `composer.json` has no `laravel/reverb` and no Prospects code
at all as of 2026-09-20 -- confirmed by checking the deployed branch
directly. `reverb:start` would just fail outright against that codebase,
and `queue:work` would have nothing real to process. `enable` without
`--now` means both will auto-start on any future reboot once real code is
there, without needing this step redone, but nothing runs prematurely now.

**Once `prospects-pipeline` is merged and deployed, start both:**

```bash
ssh root@ahmedia.ai "systemctl start dreamworldcirque-prospects-queue dreamworldcirque-prospects-reverb"
ssh root@ahmedia.ai "systemctl status dreamworldcirque-prospects-queue dreamworldcirque-prospects-reverb --no-pager"
```

Confirm both show `active (running)` (not `enabled; inactive (dead)`).

## Reverse proxy (done -- 2026-09-20)

The browser needs `wss://` (secure WebSocket) to reach Reverb from the
site's `https://` origin -- a plain `ws://` connection gets silently blocked
by the browser's mixed-content policy (confirmed locally: the connection
just sits in `CONNECTING` forever, never errors).

Rather than a path-scoped proxy on the existing `dreamworldcirque.ahmedia.ai`
vhost (risk of colliding with the Laravel app's own routes, since that
domain already serves real PHP traffic), this uses a **dedicated
subdomain** -- the same proven shape as `n8n.ahmedia.ai` and
`supabase.ahmedia.ai`, which durably survive DirectAdmin's config rewrites
via a custom `httpd.conf.d/{service}.conf` + a matching `Include` in
`virtual_host2_secure_sub.conf`. Full mechanism writeup:
`wiki/patterns/directadmin-durable-websocket-reverse-proxy.md` in the second
brain.

**Done:**
- Subdomain `dreamworldcirque-reverb.ahmedia.ai` created (DNS A record +
  DirectAdmin subdomain entry), covered automatically by the account's
  existing `*.ahmedia.ai` wildcard cert -- no separate cert request needed.
- `Include` block added to `virtual_host2_secure_sub.conf`, confirmed
  rendering correctly in the live vhost (`apachectl -S` shows
  `dreamworldcirque-reverb.ahmedia.ai` registered on both :80 and :443, and
  the vhost body has the `Include .../httpd.conf.d/dreamworldcirque-reverb.conf`
  line).
- `httpd.conf.d/dreamworldcirque-reverb.conf` created with the n8n-shaped
  ProxyPass/WebSocket rules, pointed at port **6001** (not Reverb's usual
  default of 8080 -- `ss -tlnp` showed nginx's stock, otherwise-unused
  default-site install already squatting on 8080 on this box; 6001 was
  confirmed free at the same time. Left nginx alone rather than touch a
  running production service to reclaim a port nothing needs).
- Config reloaded and re-verified: `curl -sk
  https://dreamworldcirque-reverb.ahmedia.ai/` now returns **503** (nothing
  listening on 6001 yet, as expected -- confirms the proxy target is
  correct and no longer accidentally landing on nginx's default page like
  it did during the brief window it was still pointed at 8080).

Once the Reverb systemd unit below is installed and running, that same curl
should start returning Reverb's own response instead of a 503.

`.env` on production must match the live subdomain and the corrected port:

```
REVERB_HOST=dreamworldcirque-reverb.ahmedia.ai
REVERB_PORT=443
REVERB_SCHEME=https
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

(`REVERB_PORT`/`VITE_REVERB_PORT` here are the *public* proxy port -- 443,
since the browser/Echo client talks to the Apache vhost, not directly to
Reverb's internal 6001.)

`REVERB_APP_ID`/`REVERB_APP_KEY`/`REVERB_APP_SECRET` also need real
production values (generate fresh ones -- don't reuse the local dev values
from `.env.example`'s comment/local `.env`).

## Verify end-to-end

1. Open `/admin/prospects`, DevTools Network tab, filter `WS`.
2. Open a prospect's `ViewAction` slide-over -- confirm a WS connection to
   the production Reverb route (not `localhost`).
3. Classify or approve-and-send a prospect -- confirm the UI updates without
   any 3s polling XHR loop, and that `php artisan queue:work`'s own log
   (`journalctl -u dreamworldcirque-prospects-queue -f`) shows the job
   actually running.

This last step was **not** verified live during development -- local
testing hit the same mixed-content wall this doc describes (no TLS-fronted
Reverb endpoint exists locally), so do this check for real once the reverse
proxy is live rather than assuming it works.
