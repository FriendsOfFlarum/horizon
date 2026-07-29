<?php

/*
 * This file is part of fof/horizon.
 *
 * Copyright (c) Bokt.
 * Copyright (c) Blomstra Ltd.
 * Copyright (c) FriendsOfFlarum
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Horizon;

use Flarum\Extension\ExtensionManager;
use Illuminate\Queue\QueueRoutes;

/**
 * Wires Flarum's own queued work onto the built-in supervisor profiles, so a
 * site gets a sensible split with zero configuration:
 *
 *  - core mail jobs        → `mail`     queue, served by the always-on `emails` profile
 *  - flarum/realtime jobs  → `realtime` queue, served by the `fast` profile (when enabled)
 *  - flarum/gdpr jobs      → `gdpr`     queue, served by the `long` profile (when enabled)
 *  - fof/geoip lookups     → `iplookup` queue on the always-on `standard` profile (when enabled)
 *
 * Routing a class means two things: registering it in core's queue-route map
 * (so dispatched jobs land on the right queue) and ensuring the serving profile
 * lists that queue and has workers. For the extension-conditional profiles
 * (`fast`, `long`) that also means bringing them online — they default to zero
 * processes, so without this they would stay dormant.
 *
 * Routing is registered against each extension's abstract base job class; core's
 * QueueRoutes resolves a job's queue through its class hierarchy, so routing the
 * base covers every concrete subclass. Absent classes/extensions are skipped, so
 * this is safe whether or not realtime/gdpr/geoip are installed.
 */
class BuiltInRouting
{
    /**
     * Core jobs that send mail. Routed unconditionally onto the `mail` queue.
     *
     * @var array<int, class-string>
     */
    private const MAIL_JOBS = [
        \Flarum\Notification\Job\SendEmailNotificationJob::class,
        \Flarum\Mail\Job\SendInformationalEmailJob::class,
        \Flarum\Mail\Job\SendAbandonedExtensionsEmailJob::class,
    ];

    public function __construct(
        private ExtensionManager $extensions,
        private QueueRoutes $routes
    ) {
    }

    /**
     * Apply built-in routing to the profile set: set each job's queue and make
     * sure the serving profile lists that queue (and, for the conditional
     * tiers, has workers). Returns the adjusted profiles.
     *
     * @param array<string, Supervisor> $profiles
     *
     * @return array<string, Supervisor>
     */
    public function apply(array $profiles): array
    {
        // Core mail — always on. The `emails` profile already serves `mail` and
        // ships active, so we only need to route the jobs.
        $this->route(self::MAIL_JOBS, 'mail');

        // flarum/realtime → fast/realtime, only when the extension is enabled.
        if ($this->extensions->isEnabled('flarum-realtime')) {
            $this->route([\Flarum\Realtime\Push\Jobs\Job::class], 'realtime');
            $profiles = $this->activate($profiles, 'fast', 'realtime');
        }

        // flarum/gdpr → long/gdpr, only when the extension is enabled.
        if ($this->extensions->isEnabled('flarum-gdpr')) {
            $this->route([\Flarum\Gdpr\Jobs\GdprJob::class], 'gdpr');
            $profiles = $this->activate($profiles, 'long', 'gdpr');
        }

        // fof/geoip → iplookup, only when enabled. IP lookups are light
        // background work, so — unlike realtime/gdpr — they ride the always-on
        // `standard` pool rather than a dedicated tier; we only separate them
        // onto their own queue so they don't sit behind slower default jobs.
        if ($this->extensions->isEnabled('fof-geoip')) {
            $this->route([\FoF\GeoIP\Jobs\RetrieveIP::class], 'iplookup');
            $profiles = $this->addQueue($profiles, 'standard', 'iplookup');
        }

        return $profiles;
    }

    /**
     * The queues these built-in routings put into use, filtered to the ones
     * that actually apply to this install. Fed into core's known-queues
     * registry so the dashboard and per-queue pause cover them.
     *
     * @return array<int, string>
     */
    public function activeQueues(): array
    {
        $queues = ['mail'];

        if ($this->extensions->isEnabled('flarum-realtime')) {
            $queues[] = 'realtime';
        }

        if ($this->extensions->isEnabled('flarum-gdpr')) {
            $queues[] = 'gdpr';
        }

        if ($this->extensions->isEnabled('fof-geoip')) {
            $queues[] = 'iplookup';
        }

        return $queues;
    }

    /**
     * Register each class in core's queue-route map, skipping any that isn't
     * installed.
     *
     * @param array<int, class-string> $classes
     */
    private function route(array $classes, string $queue): void
    {
        foreach ($classes as $class) {
            if (class_exists($class)) {
                $this->routes->set($class, $queue);
            }
        }
    }

    /**
     * Ensure a profile serves the given queue AND has at least one worker,
     * without disturbing a larger count an operator has already configured.
     * Use this to bring a normally-dormant tier online.
     *
     * @param array<string, Supervisor> $profiles
     *
     * @return array<string, Supervisor>
     */
    private function activate(array $profiles, string $name, string $queue): array
    {
        $profiles = $this->addQueue($profiles, $name, $queue);

        // Bring it online if it is still scaled to zero; leave a higher,
        // operator-set base untouched.
        if ($profiles[$name]->processesBase < 1) {
            $profiles[$name]->processesBase = 1;
        }

        return $profiles;
    }

    /**
     * Append a queue to a profile's queue list, leaving its worker count and
     * everything else alone. Use this to route extra work onto an already-active
     * profile (e.g. light background jobs onto `standard`) without spinning up a
     * new supervisor.
     *
     * @param array<string, Supervisor> $profiles
     *
     * @return array<string, Supervisor>
     */
    private function addQueue(array $profiles, string $name, string $queue): array
    {
        $profile = $profiles[$name] ?? new Supervisor(name: $name, queues: []);

        $profile->queues = array_values(array_unique(array_merge($profile->queues, [$queue])));

        $profiles[$name] = $profile;

        return $profiles;
    }
}
