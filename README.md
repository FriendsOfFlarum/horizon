# Horizon - Advanced Queue Dashboard & Management

![Laravel Horizon Dashboard](https://laravel.com/img/docs/horizon-example.png)

A comprehensive queue management solution for Flarum, powered by [Laravel Horizon](https://laravel.com/docs/11.x/horizon). This extension provides a beautiful dashboard for monitoring your Redis queues, along with powerful worker management and scaling capabilities.

## Features

- 📊 **Real-time Dashboard** - Beautiful web interface at `/admin/horizon`
- 🔄 **Auto-scaling Workers** - Dynamically scale workers based on queue load
- ⚖️ **Load Balancing** - Multiple balancing strategies (simple, auto, false)
- 📈 **Metrics & Insights** - Job throughput, wait times, and failure rates
- 🎯 **Job Monitoring** - Track specific jobs and tags
- 🔍 **Failed Job Management** - Retry, inspect, and clear failed jobs
- 🚦 **Supervisor Control** - Pause, continue, and terminate workers
- 📦 **Batch Job Support** - Monitor and manage job batches

## Requirements

- **Flarum 2.0+**
- **Redis Compatible Server** (required for Horizon to function)

## Installation

### Step 1: Install Redis (if not already installed)

**On Ubuntu/Debian:**

```bash
sudo apt update
sudo apt install redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

**On macOS (via Homebrew):**

```bash
brew install redis
brew services start redis
```

**Verify Redis is running:**

```bash
redis-cli ping
# Should return: PONG
```

### Step 2: Install the Extension

```bash
composer require fof/horizon:"*"
php flarum cache:clear
```

This will automatically install `fof/redis` as a dependency.

## Configuration

### Basic Setup

Horizon requires Redis to be configured. Create or modify your `extend.php` in the root of your Flarum installation:

```php
<?php

use FoF\Redis\Extend\Redis;

return [
    // Basic Redis configuration
    new Redis([
        'host'     => '127.0.0.1',
        'password' => null,
        'port'     => 6379,
        'database' => 0,
    ]),

    // ... other extenders
];
```

### Advanced Redis Configuration

**Using separate databases for different services:**

```php
return [
    (new Redis([
        'host'     => '127.0.0.1',
        'password' => null,
        'port'     => 6379,
    ]))
    ->useDatabaseWith('cache', 1)
    ->useDatabaseWith('queue', 2)
    ->useDatabaseWith('session', 3),
];
```

**Disabling specific Redis features:**

```php
return [
    (new Redis([
        'host' => '127.0.0.1',
        'port' => 6379,
    ]))
    ->disable(['cache']), // Only use Redis for queue and sessions
];
```

**Redis Sentinel (High Availability):**

```php
return [
    new Redis([
        'host' => '127.0.0.1',
        'port' => 26379,
        'options' => [
            'replication' => 'sentinel',
            'service'     => 'mymaster',
        ],
    ]),
];
```

### Supervisor profiles

Horizon organises workers into **supervisor profiles** — named worker pools,
each tuned for a class of job. Four profiles ship out of the box:

| Profile | Timeout | Purpose | Default state |
|---|---|---|---|
| `standard` | 60s | Regular queue jobs — ordinary background work | **active** (base 1 × 6 processes) |
| `emails` | 120s | Outbound mail; retries transient failures (`tries: 3`) | **active**, serves the `mail` queue |
| `fast` | 3s | Quick jobs that mustn't back up — the short timeout kills a hung one early rather than clogging the lane | active **when `flarum/realtime` is enabled** |
| `long` | 3600s | Heavy lifting — slow, resource-hungry jobs; 4× memory | active **when `flarum/gdpr` is enabled** |

**Sensible defaults, no configuration.** Horizon wires Flarum's own queued work
onto these profiles automatically:

- **`standard`** runs by default for general work.
- **`emails`** runs by default and serves the `mail` queue — every install sends
  mail, so core's mail jobs (notifications, informational emails) are routed
  onto it out of the box. It's capped by your mail provider's connection budget
  rather than host CPU, so it uses a small fixed process count and retries
  (mail failures are often transient).
- **`fast`** comes online automatically when **`flarum/realtime`** is enabled,
  serving a `realtime` queue that realtime's push jobs are routed onto.
- **`long`** comes online automatically when **`flarum/gdpr`** is enabled,
  serving a `gdpr` queue that GDPR's export/erasure jobs are routed onto.
- **`fof/geoip`**, when enabled, gets an `iplookup` queue — but this one rides
  the always-on `standard` pool rather than a dedicated tier. IP lookups are
  light background work; the separate queue just stops them sitting behind
  slower `default` jobs, at no extra worker cost.

Absent extensions are simply skipped, so this is safe whichever ones you run.

**Scale to zero.** A profile with no queue routed to it and no workers stays
dormant and costs nothing — that's why `fast` and `long` do nothing until their
extension is present (or you bring them online yourself). You can raise any
profile's process count, route more queues onto it, or define new profiles; see
below. A profile given processes but no queues is a configuration error and
Horizon will refuse to start (see
[Failing fast](#failing-fast-on-misconfiguration)).

### Sizing the Horizon host

Horizon runs a pool of **worker processes**, and each one is a full PHP process
that uses a CPU core when busy and holds its own memory. Size the host,
container or VM that runs Horizon around the workers you have active — under-
provisioning shows up as the OS OOM-killing workers or jobs crawling because
every worker is fighting for a core.

**Memory** is the easy one to get wrong: it's *(processes × per-worker memory)*,
summed across active profiles, plus the master and general PHP overhead. With
the built-in defaults:

| Active profile | Processes | Memory / worker | Subtotal |
|---|---|---|---|
| `standard` (always) | 6 | 128 MB | 768 MB |
| `emails` (always) | 1 | 128 MB | 128 MB |
| `fast` (if realtime enabled) | 12 | 128 MB | 1536 MB |
| `long` (if gdpr enabled) | 1 | 512 MB | 512 MB |

So a **fresh install** wants on the order of **~1 GB** of RAM for the queue
alone (≈900 MB of workers plus headroom); with realtime and gdpr enabled and at
default scaling that climbs toward **~3 GB**. These are ceilings — the
`auto`/`simple` balancers only run as many workers as there is work for — but
provision for the ceiling so a burst doesn't get OOM-killed. Because `long`
workers each get a 512 MB budget, Horizon automatically raises the worker
`memory_limit` to the largest supervisor budget (see
[Automatic worker memory limit](#automatic-worker-memory-limit)).

**CPU:** each busy worker saturates roughly one core, so the total worker count
across active profiles is a good guide to how many cores you want available.
You do **not** need one core per worker — queue work is bursty and often
I/O-bound (waiting on the database, mail server or HTTP) — but a host with far
fewer cores than busy workers will see jobs queue behind CPU rather than run in
parallel. A useful rule of thumb: **cores ≈ the number of workers you expect
busy at once**, which for most forums is well below the configured maximum.

**Scale to your box, not the defaults.** The defaults suit a modest dedicated
host. If you run Horizon somewhere smaller — a shared container, a 1 GB VPS —
turn the process counts *down* (e.g. `REDIS_HORIZON_STANDARD_MAX_PROCESSES=2`)
rather than leaving the defaults and hoping; a handful of workers that fit in
RAM beats a dozen that get killed. See
[Configuration layers](#configuration-layers) for how.

### Controlling email throughput

The most common thing you'll want to cap is **how many emails go out at once** —
mail providers limit concurrent connections, and exceeding that gets you
throttled or blocked. Because each email worker sends one message at a time,
this is simply the `emails` profile's worker count, and Horizon gives it a
dedicated, purpose-named control on every surface (highest precedence first):

```bash
# Environment variable
REDIS_HORIZON_EMAIL_CONCURRENCY=4
```

```php
// config.php
'horizon' => [
    'email_concurrency' => 4,
],
```

```php
// extend.php
(new FoF\Horizon\Extend\Horizon)->emailConcurrency(4),
```

…or the **Simultaneous outgoing emails** field on the Horizon admin page. Set it
to your provider's concurrent-connection limit. It defaults to 1 and takes
precedence over a generic `emails` process count, so it's the single knob to
reach for.

### Configuration layers

Every tunable resolves through four layers, highest precedence first, so you
can pin values per deployment without touching the database:

1. **Environment variables** (highest) — ideal for Docker/Kubernetes
2. **config.php** — under the `horizon` key
3. **extend.php** — the `Horizon` extender (see below)
4. Built-in profile defaults (lowest)

#### Scaling a profile with environment variables

Per-profile scaling uses `REDIS_HORIZON_<PROFILE>_*` variables and a
**base × multiplier** model. A literal `MAX_PROCESSES` / `MEMORY_LIMIT` wins
outright; otherwise the base is multiplied by the multiplier:

| Variable | Meaning |
|---|---|
| `REDIS_HORIZON_<PROFILE>_MAX_PROCESSES` | Literal process count (overrides the multiplier) |
| `REDIS_HORIZON_<PROFILE>_PROCESSES_MULTIPLIER` | Multiplier applied to the base process count |
| `REDIS_HORIZON_<PROFILE>_MEMORY_LIMIT` | Literal per-process memory in MB |
| `REDIS_HORIZON_<PROFILE>_MEMORY_MULTIPLIER` | Multiplier applied to the base memory |

`<PROFILE>` is the upper-cased profile name — `STANDARD`, `FAST`, `LONG`,
`EMAILS`, or your own. Global (non-profile) tunables keep a plain
`REDIS_HORIZON_*` name:

| Setting | config.php (`horizon.` key) | Environment variable | Default |
|---|---|---|---|
| Master memory limit (MB) | `memory_limit` | `REDIS_HORIZON_MEMORY_LIMIT` | 128 (auto-raised, see below) |
| Trim settings (minutes) | `trim.*` | `REDIS_HORIZON_TRIM_*` | 60 / 10080 |

> **Note:** `REDIS_*` (without `HORIZON_`) belongs to
> [FoF Redis](https://github.com/FriendsOfFlarum/redis) — that's your Redis
> *connection* (host, port, database, prefix). `REDIS_HORIZON_*` is Horizon's
> *worker scaling*. Two concerns, two prefixes.

Bring the `fast` tier online with three workers, for example:

```bash
REDIS_HORIZON_FAST_MAX_PROCESSES=3
```

#### Configuring profiles in config.php

Environment variables set scaling; `config.php` can set anything else on a
profile — the queues it serves, its balance strategy, timeout, or any Horizon
key. Scaling values here are overridden by the matching env var.

```php
'horizon' => [
    'supervisors' => [
        // Bring `fast` online and point it at your realtime queue.
        'fast' => [
            'queues'    => ['realtime'],
            'processes' => 3,
        ],
        // A heavy tier for bulk work.
        'long' => [
            'queues' => ['exports', 'gdpr'],
        ],
    ],
    'memory_limit' => 256,
],
```

#### Configuring profiles in extend.php

The `Horizon` extender is the most expressive surface — it also lets you route
jobs onto queues and register brand-new profiles:

```php
<?php

use FoF\Horizon\Extend\Horizon;

return [
    (new Horizon)
        // Route a job onto a queue. This sets the job's queue AND registers it
        // so the dashboard and per-queue pause know about it. If the job class
        // isn't installed it's skipped, so optional dependencies are safe.
        //
        // Pass a third argument to also attach that queue to a supervisor in one
        // go — e.g. put a translation job's queue on the always-on `standard`
        // pool. Without it, the job is routed but no worker consumes the queue
        // until a supervisor serves it.
        ->routeJob(\Your\Extension\Jobs\TranslateJob::class, 'translate', 'standard')
        ->routeJob(\Your\Extension\Jobs\RealtimeJob::class, 'realtime')

        // Routing an abstract base (or interface) covers every job that extends
        // (or implements) it — route the base once and all its concrete
        // subclasses inherit the queue. A more specific route on a subclass
        // still wins over the base.
        ->routeJob(\Your\Extension\Jobs\AbstractExportJob::class, 'long')

        // Add extra queues under an existing profile without touching its other
        // settings — handy for base images layering their own queues on.
        ->queueOn('long', 'exports', 'gdpr')

        // Override a profile's knobs. Recognised keys map onto the profile;
        // anything else (nice, balanceMaxShift, retry_after, …) passes straight
        // through to Horizon.
        ->supervisor('fast', ['queues' => ['realtime'], 'processes' => 12])

        // Define a brand-new profile.
        ->supervisor('media', ['queues' => ['thumbnails'], 'timeout' => 300, 'processes' => 2]),
];
```

##### Adding queues to a supervisor

A very common need — especially for base images and site skeletons — is to run
your own extra queues on an existing profile without redefining it. `queueOn()`
**appends** to a supervisor's queue list, leaving its worker count and every
other setting untouched:

```php
return [
    // Put three extra queues on the `long` profile alongside whatever it
    // already serves. Repeated/duplicate names are de-duplicated.
    (new FoF\Horizon\Extend\Horizon)
        ->queueOn('long', 'exports', 'gdpr', 'migration'),
];
```

The appended queues are also registered with core so the dashboard and
per-queue pause cover them. Use `queueOn()` when you just want to add work to a
tier; use `supervisor(name, ['queues' => [...]])` when you want to *set* (and
thereby replace) a profile's full queue list.

To start from a blank slate instead of the four built-in profiles, call
`->withoutDefaultProfiles()`.

#### Taking full manual control (`useRawConfig`)

If the profile model doesn't fit your setup, bypass it entirely and hand-write
the worker layout with `useRawConfig()`. Pass the supervisor map for the
current environment (supervisor name => options); fof/horizon keys it under the
running environment for you, so you don't hardcode `production`/`testing`/etc.
This **replaces** the profile system: no default profiles, no automatic job
routing.

```php
(new FoF\Horizon\Extend\Horizon)->useRawConfig([
    'supervisor-1' => [
        'connection'   => 'redis',
        'queue'        => ['default'],
        'balance'      => 'auto',
        'maxProcesses' => 10,
        // ...any Laravel Horizon supervisor options
    ],
]);
```

> **Note:** `->config()` remains available for other top-level Horizon keys
> (e.g. `fast_termination`, `waits`), but it can **no longer** set the worker
> layout — passing an `environments`/`supervisors` array to it (or
> `->environment()`, or `config.php`'s `horizon.environments`) throws at boot,
> because the profile system owns that. Use profiles or `useRawConfig()`
> instead. See [Breaking changes](#breaking-changes-supervisor-profiles).

#### Automatic worker memory limit

Horizon raises the forked worker's PHP `memory_limit` to the largest supervisor
`memory` budget automatically. Without this, a worker whose supervisor budget
exceeds the CLI `memory_limit` hits PHP's fatal "Allowed memory size exhausted"
*before* Horizon's graceful memory check can restart it. An explicit
`REDIS_HORIZON_MEMORY_LIMIT` still wins if you set it higher.

#### Failing fast on misconfiguration

Horizon validates scaling at boot and **throws** (rather than silently falling
back to a default) when:

- a `REDIS_HORIZON_*` scaling value is not a positive integer, or
- a profile is given processes but no queues (it would otherwise silently drain
  `default`, duplicating the `standard` pool).

A misconfigured worker fleet should fail loudly at startup, not quietly run the
wrong shape.

> **Note:** Supervisor settings are read when Horizon starts. Restart Horizon
> (`php flarum horizon:terminate`) after changing them.

## Running Horizon

### Development (Manual)

Start Horizon from your Flarum root directory:

```bash
php flarum horizon
```

This will run as long as the terminal session is active. Press `Ctrl+C` to stop.

### Production (Supervisor)

For production environments, use a process monitor like **Supervisor** to keep Horizon running:

**1. Install Supervisor:**

```bash
# Ubuntu/Debian
sudo apt install supervisor

# macOS
brew install supervisor
brew services start supervisor
```

**2. Create a Supervisor configuration** (`/etc/supervisor/conf.d/horizon.conf`):

```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/flarum/flarum horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/flarum/storage/logs/horizon.log
stopwaitsecs=3600
```

**3. Start Supervisor:**

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon
```

**4. Check status:**

```bash
sudo supervisorctl status horizon
```

### Systemd (Alternative)

Create a systemd service file (`/etc/systemd/system/flarum-horizon.service`):

```ini
[Unit]
Description=Flarum Horizon
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/flarum
ExecStart=/usr/bin/php /var/www/flarum/flarum horizon
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable flarum-horizon
sudo systemctl start flarum-horizon
sudo systemctl status flarum-horizon
```

## Usage

### Accessing the Dashboard

Navigate to: `https://yourforum.com/admin/horizon`

### Available Commands

**Start Horizon:**

```bash
php flarum horizon
```

**View active supervisors:**

```bash
php flarum horizon:list
```

**Pause all workers:**

```bash
php flarum horizon:pause
```

**Continue processing after pause:**

```bash
php flarum horizon:continue
```

**Pause a specific supervisor:**

```bash
php flarum horizon:pause-supervisor supervisor-1
```

**Continue a specific supervisor:**

```bash
php flarum horizon:continue-supervisor supervisor-1
```

**View Horizon status:**

```bash
php flarum horizon:status
```

**View supervisor status:**

```bash
php flarum horizon:supervisor-status supervisor-1
```

**Terminate all workers gracefully:**

```bash
php flarum horizon:terminate
```

The terminate signal is broadcast through Redis, so it reaches Horizon
wherever it runs — including a different container or host than the one the
command is issued from.

**Clear all jobs from a queue:**

```bash
php flarum horizon:clear redis --queue=default
```

**Purge failed jobs:**

```bash
php flarum horizon:purge
```

**Delete a specific failed job:**

```bash
php flarum horizon:forget {job-id}
```

**Delete all failed jobs:**

```bash
php flarum horizon:forget --all
```

**Clear metrics:**

```bash
php flarum horizon:clear-metrics
```

## Dashboard Features

### Real-time Monitoring

- **Jobs Per Minute** - Current throughput
- **Pending Jobs** - Jobs waiting to be processed
- **Failed Jobs** - Recent failures
- **Processes** - Active worker processes
- **Wait Time** - Average queue wait time
- **Status** - Horizon operational status (running/paused/inactive)

### Job Management

- **View pending jobs** - See what's in the queue
- **View completed jobs** - Recent successful jobs
- **View failed jobs** - Inspect failures with full stack traces
- **Retry failed jobs** - Re-queue failed jobs
- **Monitor specific tags** - Track tagged jobs

### Metrics

- **Job Metrics** - Performance stats per job type
- **Queue Metrics** - Stats per queue
- **Throughput** - Jobs processed over time
- **Runtime** - Job execution times

## Troubleshooting

### Dashboard shows errors

**Issue:** 500 errors or "Redis not configured" messages

**Solution:** Ensure Redis is properly configured in `extend.php`:

```bash
# Test Redis connection
redis-cli ping

# Check Flarum logs
tail -f storage/logs/flarum-*.log
```

### Workers not processing jobs

**Issue:** Jobs remain in pending state

**Solution:**

1. Ensure Horizon is running: `sudo supervisorctl status horizon`
2. Restart Horizon: `php flarum horizon:terminate` then start again
3. Check for errors: `tail -f storage/logs/horizon.log`

### After deployment, workers process old code

**Issue:** Code changes not reflected in running workers

**Solution:** For code-only deployments, gracefully recycle the worker
processes — the Horizon master stays up and respawns them with the new code:

```bash
php flarum queue:restart
```

If Horizon's own configuration changed (supervisor settings, queues, memory
limits), restart the master instead:

```bash
php flarum horizon:terminate
# Your process monitor (Supervisor/systemd/container runtime) restarts it
```

Both signals travel via Redis/cache, so they work across containers.

### Memory issues

**Issue:** Workers consuming too much memory, or you see `Memory limit exceeded: Using X/128MB. Consider increasing horizon.memory_limit.`

There are two separate memory limits:

- **`memory_limit`** — the master supervisor process. When exceeded, Horizon restarts itself gracefully. Default: 128 MB.
- **`memory`** (per supervisor) — each individual worker process. When exceeded after a job completes, the worker is recycled. Default: 128 MB.

**Solution:** Raise the master limit via `config.php`, and the per-worker limit
on the relevant profile:

```php
'horizon' => [
    'memory_limit' => 256, // MB — master supervisor
    'supervisors' => [
        'standard' => [
            'memory' => 256, // MB — per worker in this profile
        ],
    ],
],
```

Note that Horizon already raises the master `memory_limit` to the largest
per-worker budget automatically, so you rarely need to set it by hand — see
[Automatic worker memory limit](#automatic-worker-memory-limit).

You can also limit how long a worker runs before being recycled by passing the
standard Horizon keys through a profile:

```php
'horizon' => [
    'supervisors' => [
        'standard' => [
            'maxJobs' => 1000, // restart the worker after X jobs
            'maxTime' => 3600, // restart the worker after X seconds
        ],
    ],
],
```

## Performance Tips

1. **Use Auto-scaling:** Set `'balance' => 'auto'` to automatically scale workers based on load
2. **Separate Queues:** Use different queues for high/low priority jobs
3. **Set Memory Limits:** Configure `memory` to restart workers before they consume too much
4. **Monitor Metrics:** Regularly check the dashboard for bottlenecks
5. **Tag Jobs:** Use tags to group and monitor related jobs

## Security

- The Horizon dashboard is automatically protected by Flarum's admin authentication
- No additional security configuration needed
- Only forum administrators can access `/admin/horizon`

## Upgrading

```bash
composer update fof/horizon
php flarum migrate
php flarum cache:clear
php flarum horizon:terminate
```

### Breaking changes: supervisor profiles

The worker configuration model changed. Sites that customised workers must
migrate — the old settings no longer have any effect (there is no automatic
fallback):

- **`FOF_HORIZON_*` environment variables are gone.** Per-profile scaling now
  uses `REDIS_HORIZON_<PROFILE>_*` (see
  [Scaling a profile with environment variables](#scaling-a-profile-with-environment-variables)),
  and global tunables use `REDIS_HORIZON_*`. Migrate, e.g.
  `FOF_HORIZON_PROCESSES=10` → `REDIS_HORIZON_STANDARD_MAX_PROCESSES=10`,
  `FOF_HORIZON_QUEUES=...` → route those queues onto a profile.
- **The `fof-horizon.supervisor.*` admin settings are gone.** Configure
  profiles via `config.php` or the `Horizon` extender instead.
- **`config.php` uses `horizon.supervisors` (plural), keyed by profile name.**
  The old single `horizon.supervisor` block is replaced by the profile model.
- **A hand-written worker layout now throws instead of being silently ignored.**
  The profile system owns Horizon's `environments`, so passing an
  `environments`/`supervisors` array to the extender's `->config()` or
  `->environment()`, or setting `horizon.environments` in `config.php`, throws
  at boot with a pointer to the supported paths. This is deliberate: previously
  such a config was quietly dropped and the site ran the wrong layout. **Migrate
  to profiles** (`supervisor()`/`routeJob()`/`queueOn()`, or
  `config.php`'s `horizon.supervisors`), or, to keep hand-writing the full
  layout, move it to [`useRawConfig()`](#taking-full-manual-control-userawconfig),
  which bypasses the profile system. `->config()` still works for other
  top-level Horizon keys.
- **Setting a job's queue via its static `$onQueue` property no longer works.**
  Job routing now goes through Flarum core's queue-route map, which
  Horizon's `routeJob()` writes to for you. If you previously set a queue by
  assigning to a job class's static property — including on a shared abstract
  base — replace it with `->routeJob(JobClass::class, 'queue')`. Routing the
  abstract base still covers all its subclasses (see
  [Configuring profiles in extend.php](#configuring-profiles-in-extendphp)), and
  because routing is now keyed per class rather than shared through a static,
  routing several job classes no longer collides.

After upgrading, run `php flarum horizon:terminate` so the master restarts with
the new configuration.

Then restart Horizon (via Supervisor or manually).

## Migration from Blomstra Redis

If you're upgrading from the older `blomstra/redis` package:

1. Simply update namespace references from `Blomstra\Redis` to `FoF\Redis` in your `extend.php`
2. All configuration options remain the same

## Links

- **Packagist:** [fof/horizon](https://packagist.org/packages/fof/horizon)
- **GitHub:** [FriendsOfFlarum/horizon](https://github.com/FriendsOfFlarum/horizon)
- **Discuss:** [Flarum Community](https://discuss.flarum.org/d/27520)
- **Documentation:** [Laravel Horizon Docs](https://laravel.com/docs/horizon)

## License

MIT

---

**Made with ❤️ by [FriendsOfFlarum](https://friendsofflarum.org)**
