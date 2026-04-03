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
- **Redis Server** (required for Horizon to function)
- **PHP 8.2+**

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

### Customizing Horizon Configuration

You can override Horizon's default configuration by creating a custom config file:

**1. Create a Horizon config file** (e.g., `config/horizon.php` in your Flarum root):

```php
<?php

return [
    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 10,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 20,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],
    ],
];
```

**2. Register your config in `extend.php`:**

```php
<?php

use FoF\Horizon\Extend\Horizon;

return [
    (new Horizon)->config('./config/horizon.php'),
];
```

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

**Solution:** Always terminate and restart Horizon after deployments:

```bash
php flarum horizon:terminate
# Wait a few seconds for graceful shutdown
php flarum horizon
```

Or via Supervisor:

```bash
sudo supervisorctl restart horizon
```

### Memory issues

**Issue:** Workers consuming too much memory, or you see `Memory limit exceeded: Using X/128MB. Consider increasing horizon.memory_limit.`

There are two separate memory limits:

- **`memory_limit`** — the master supervisor process. When exceeded, Horizon restarts itself gracefully. Default: 128 MB.
- **`memory`** (per supervisor) — each individual worker process. When exceeded after a job completes, the worker is recycled. Default: 128 MB.

**Solution:** Override either limit via `config.php`:

```php
'horizon' => [
    'memory_limit' => 256, // MB — master supervisor
],
```

Or via the `Horizon` extender in `extend.php`:

```php
(new \FoF\Horizon\Extend\Horizon)->config([
    'memory_limit' => 256, // MB — master supervisor
    'environments' => [
        'production' => [
            'supervisor-1' => [
                'memory' => 256, // MB — per worker
            ],
        ],
    ],
]),
```

You can also limit how long a worker runs before being recycled:

```php
'defaults' => [
    'supervisor-1' => [
        'maxJobs' => 1000, // Restart after X jobs
        'maxTime' => 3600, // Restart after X seconds
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

Then restart Horizon (via Supervisor or manually).

## Migration from Blomstra Redis

If you're upgrading from the older `blomstra/redis` package:

1. Simply update namespace references from `Blomstra\Redis` to `FoF\Redis` in your `extend.php`
2. All configuration options remain the same

## Links

- **Packagist:** [fof/horizon](https://packagist.org/packages/fof/horizon)
- **GitHub:** [FriendsOfFlarum/horizon](https://github.com/FriendsOfFlarum/horizon)
- **Discuss:** [Flarum Community](https://discuss.flarum.org/d/27520)
- **Documentation:** [Laravel Horizon Docs](https://laravel.com/docs/11.x/horizon)

## License

MIT

---

**Made with ❤️ by [FriendsOfFlarum](https://friendsofflarum.org)**
