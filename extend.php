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

use Flarum\Extend as Flarum;
use FoF\Redis\Extend\Bindings;
use Illuminate\Console\Scheduling\Event;
use Laravel\Horizon\Console as Laravel;

return [
    (new Flarum\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/resources/less/admin.less')
        ->content(Content\AdminContent::class),

    new Flarum\Locales(__DIR__.'/resources/locale'),

    new Bindings(),

    (new Flarum\Settings())
        ->default('fof-horizon.trim.recent', 60)
        ->default('fof-horizon.trim.pending', 60)
        ->default('fof-horizon.trim.completed', 60)
        ->default('fof-horizon.trim.recent_failed', 10080)
        ->default('fof-horizon.trim.failed', 10080)
        ->default('fof-horizon.trim.monitored', 10080),

    (new Flarum\ServiceProvider())
        ->register(Providers\HorizonServiceProvider::class),

    (new Flarum\Console())
        ->command(Laravel\HorizonCommand::class)
        ->command(Laravel\ListCommand::class)
        ->command(Laravel\PurgeCommand::class)
        ->command(Laravel\PauseCommand::class)
        ->command(Laravel\ContinueCommand::class)
        ->command(Laravel\StatusCommand::class)
        ->command(Laravel\SupervisorCommand::class)
        ->command(Laravel\SupervisorsCommand::class)
        ->command(Laravel\TerminateCommand::class)
        ->command(Laravel\TimeoutCommand::class)
        ->command(Console\WorkCommand::class)
        ->command(Laravel\SnapshotCommand::class)
        ->command(Laravel\ClearMetricsCommand::class)
        ->schedule(Laravel\SnapshotCommand::class, function (Event $schedule) {
            $schedule->everyMinute()->onOneServer()->withoutOverlapping();
        }),
    // Routes
    (new Flarum\Routes('admin'))
        // Dashboard Routes...
        ->get('/horizon/api/stats', 'horizon.stats.index', Api\Stats::class)
        // Workload Routes...
        ->get('/horizon/api/workload', 'horizon.workload.index', Api\Workload::class)
        // Master Supervisor Routes...
        ->get('/horizon/api/masters', 'horizon.masters.index', Api\Masters::class)
        ->get('/horizon/api/monitoring', 'horizon.monitoring.index', Api\Monitoring::class)
        // Monitoring Routes...
        ->post('/horizon/api/monitoring', 'horizon.monitoring.store', Api\MonitorTag::class)
        ->get('/horizon/api/monitoring/{tag}', 'horizon.monitoring-tag.paginate', Api\TagMonitoring::class)
        ->delete('/horizon/api/monitoring/{tag}', 'horizon.monitoring-tag.destroy', Api\StopMonitoringTag::class)
        // Job Metric Routes...
        ->get('/horizon/api/metrics/jobs', 'horizon.jobs-metrics.index', Api\Metrics::class)
        ->get('/horizon/api/metrics/jobs/{id}', 'horizon.jobs-metrics.show', Api\JobMetrics::class)
        // Queue Metric Routes...
        ->get('/horizon/api/metrics/queues', 'horizon.queues-metrics.index', Api\QueueMetrics::class)
        ->get('/horizon/api/metrics/queues/{id}', 'horizon.queues-metrics.show', Api\QueueJobMetrics::class)
        // Batches Routes...
        ->get('/horizon/api/batches', 'horizon.jobs-batches.index', Api\Batches::class)
        ->get('/horizon/api/batches/{id}', 'horizon.jobs-batches.show', Api\Batch::class)
        ->post('/horizon/api/batches/retry/{id}', 'horizon.jobs-batches.retry', Api\RetryBatch::class)
        // Job Routes...
        ->get('/horizon/api/jobs/pending', 'horizon.pending-jobs.index', Api\PendingJobs::class)
        ->get('/horizon/api/jobs/completed', 'horizon.completed-jobs.index', Api\CompletedJobs::class)
        ->get('/horizon/api/jobs/silenced', 'horizon.silenced-jobs.index', Api\SilencedJobs::class)
        ->get('/horizon/api/jobs/failed', 'horizon.failed-jobs.index', Api\FailedJobs::class)
        ->get('/horizon/api/jobs/failed/{id}', 'horizon.failed-jobs.show', Api\FailedJob::class)
        ->post('/horizon/api/jobs/retry/{id}', 'horizon.retry-jobs.show', Api\RetryJob::class)
        ->get('/horizon/api/jobs/{id}', 'horizon.jobs.show', Api\Job::class)

        ->get('/horizon', 'horizon.index', Http\Home::class)
        ->get('/horizon/{view:.*}', 'horizon.index.view', Http\Home::class),
    // Assets
    new Extend\PublishAssets(),

    (new Flarum\View())
        ->namespace('horizon', __DIR__.'/resources/views'),
];
