import { NestedStringArray } from '@askvortsov/rich-icu-message-formatter';
import app from 'flarum/admin/app';
import DashboardWidget, { IDashboardWidgetAttrs } from 'flarum/admin/components/DashboardWidget';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Mithril from 'mithril';
import LinkButton from 'flarum/common/components/LinkButton';
import Tooltip from 'flarum/common/components/Tooltip';
import Switch from 'flarum/common/components/Switch';
import icon from 'flarum/common/helpers/icon';
import ItemList from 'flarum/common/utils/ItemList';
import humanTime from 'flarum/common/utils/humanTime';

export default class HorizonStatsWidget extends DashboardWidget {
  loading = true;
  data: any = {};
  previousData: any = {};
  autoRefreshEnabled = false;
  autoRefreshInterval?: number;
  refreshInterval = 5000; // Default 5 seconds
  lastRefreshTime?: number;

  oncreate(vnode: Mithril.Vnode<IDashboardWidgetAttrs>) {
    super.oncreate(vnode);

    // Load auto-refresh preference from localStorage
    const savedAutoRefresh = localStorage.getItem('horizonAutoRefresh');
    if (savedAutoRefresh === 'true') {
      this.autoRefreshEnabled = true;
    }

    // Load refresh interval preference from localStorage
    const savedInterval = localStorage.getItem('horizonRefreshInterval');
    if (savedInterval) {
      this.refreshInterval = parseInt(savedInterval, 10);
    }

    this.loadHorizonStats();

    if (this.autoRefreshEnabled) {
      this.setAutoRefresh();
    }
  }

  onremove() {
    this.clearAutoRefresh();
  }

  async loadHorizonStats() {
    this.loading = true;
    m.redraw();

    try {
      const data = (await app.request({
        method: 'GET',
        url: app.forum.attribute('adminUrl') + '/horizon/api/stats',
      })) as any;

      // Store previous data for trend comparison before updating
      if (this.data && !this.data.error && Object.keys(this.data).length > 0) {
        this.previousData = { ...this.data };
      }

      this.data = data;
      this.lastRefreshTime = Date.now();

      // If we get an error in the response, disable auto-refresh
      if (data.error !== undefined && this.autoRefreshEnabled) {
        this.toggleAutoRefresh(false);
      }
    } catch (error) {
      console.error('Failed to load Horizon stats:', error);
      this.data = { error: app.translator.trans('fof-horizon.admin.stats.error.fetch_failed') };

      // Disable auto-refresh on network errors
      if (this.autoRefreshEnabled) {
        this.toggleAutoRefresh(false);
      }
    }

    this.loading = false;
    m.redraw();
  }

  toggleAutoRefresh(enabled: boolean) {
    this.autoRefreshEnabled = enabled;
    // Persist preference
    localStorage.setItem('horizonAutoRefresh', enabled ? 'true' : 'false');

    if (enabled) {
      this.setAutoRefresh();
    } else {
      this.clearAutoRefresh();
    }
  }

  setAutoRefresh() {
    this.clearAutoRefresh();
    this.autoRefreshInterval = setInterval(() => this.loadHorizonStats(), this.refreshInterval) as unknown as number;
  }

  setRefreshInterval(interval: number) {
    this.refreshInterval = interval;
    localStorage.setItem('horizonRefreshInterval', interval.toString());

    // If auto-refresh is enabled, restart it with new interval
    if (this.autoRefreshEnabled) {
      this.setAutoRefresh();
    }
  }

  clearAutoRefresh() {
    if (this.autoRefreshInterval) {
      clearInterval(this.autoRefreshInterval as unknown as NodeJS.Timeout);
      this.autoRefreshInterval = undefined;
    }
  }

  className() {
    return 'HorizonStatsWidget';
  }

  content() {
    const hasError = this.data.error !== undefined;

    return (
      <div className="HorizonStatsWidget-container">
        <div className="HorizonStatsWidget-header">
          <div className="HorizonStatsWidget-titleSection">
            <h4 className="HorizonStatsWidget-title">
              {icon('fas fa-chart-bar')} {app.translator.trans('fof-horizon.admin.stats.heading')}
            </h4>
            {this.renderLastRefresh()}
          </div>
          <div className="HorizonStatsWidget-controls">
            <Tooltip text={app.translator.trans('fof-horizon.admin.stats.refresh')}>
              <Button
                className="Button Button--icon"
                icon="fas fa-sync-alt"
                onclick={() => this.loadHorizonStats()}
                disabled={this.loading || this.autoRefreshEnabled}
              />
            </Tooltip>

            <LinkButton
              className="Button"
              icon="fas fa-external-link-alt"
              href={app.forum.attribute<string>('adminUrl') + '/horizon'}
              target="_blank"
              external={true}
              disabled={hasError}
            >
              {app.translator.trans('fof-horizon.admin.stats.full_dashboard')}
            </LinkButton>
          </div>
        </div>
        {hasError ? this.renderError() : <div className="HorizonStatsWidget-grid">{this.renderStatsSection()}</div>}
        {!hasError && (
          <div className="HorizonStatsWidget-footer">
            <div className="HorizonStatsWidget-footerLeft">
              <Switch state={this.autoRefreshEnabled} onchange={this.toggleAutoRefresh.bind(this)} loading={this.loading}>
                {app.translator.trans('fof-horizon.admin.stats.auto_refresh')}
              </Switch>
              {this.autoRefreshEnabled && this.renderRefreshIntervalSelector()}
            </div>
            {this.renderHealthIndicator()}
          </div>
        )}
      </div>
    );
  }

  renderLastRefresh() {
    if (!this.lastRefreshTime) return null;

    const timeAgo = humanTime(this.lastRefreshTime);

    return (
      <small className="HorizonStatsWidget-lastRefresh">
        {icon('fas fa-clock')} {app.translator.trans('fof-horizon.admin.stats.last_refresh', { time: timeAgo })}
      </small>
    );
  }

  renderRefreshIntervalSelector() {
    const intervals = [
      { value: 5000, label: '5s' },
      { value: 10000, label: '10s' },
      { value: 30000, label: '30s' },
      { value: 60000, label: '1m' },
    ];

    return (
      <div className="HorizonStatsWidget-intervalSelector">
        {intervals.map((interval) => (
          <Button
            className={`Button Button--sm ${this.refreshInterval === interval.value ? 'active' : ''}`}
            onclick={() => this.setRefreshInterval(interval.value)}
          >
            {interval.label}
          </Button>
        ))}
      </div>
    );
  }

  renderHealthIndicator() {
    const healthScore = this.data.healthScore;
    if (healthScore === undefined) return null;

    let healthClass = 'success';
    let healthLabel = app.translator.trans('fof-horizon.admin.stats.health.excellent');

    if (healthScore < 50) {
      healthClass = 'danger';
      healthLabel = app.translator.trans('fof-horizon.admin.stats.health.critical');
    } else if (healthScore < 70) {
      healthClass = 'warning';
      healthLabel = app.translator.trans('fof-horizon.admin.stats.health.poor');
    } else if (healthScore < 85) {
      healthClass = 'info';
      healthLabel = app.translator.trans('fof-horizon.admin.stats.health.good');
    }

    return (
      <Tooltip text={app.translator.trans('fof-horizon.admin.stats.health.tooltip', { score: healthScore })}>
        <div className={`HorizonStatsWidget-health HorizonStatsWidget-health--${healthClass}`}>
          {icon('fas fa-heartbeat')} {healthLabel}
        </div>
      </Tooltip>
    );
  }

  renderError() {
    const { error } = this.data;

    return (
      <div className="HorizonStatsWidget-error Alert Alert--danger">
        <strong>
          {icon('fas fa-exclamation-triangle')}
          {app.translator.trans('fof-horizon.admin.stats.error.title')}
        </strong>
        <p>{error}</p>
        <p>
          <LinkButton
            className="Button Button--link"
            icon="fas fa-book"
            href="https://github.com/FriendsOfFlarum/redis?tab=readme-ov-file#set-up"
            external={true}
            target="_blank"
          >
            {app.translator.trans('fof-horizon.admin.stats.error.setup_docs')}
          </LinkButton>
        </p>
      </div>
    );
  }

  renderStatsSection() {
    const {
      jobsPerMinute,
      recentJobs,
      recentlyFailed,
      status,
      processes,
      queueWithMaxRuntime,
      queueWithMaxThroughput,
      maxWaitTime,
      maxWaitQueue,
      failureRate,
      successRate,
    } = this.data;
    const redis_stats = this.data.redis_stats ?? {};

    return (
      <>
        {this.renderStatusIndicator(status)}

        {/* Memory Stats with threshold warnings */}
        {this.renderStat(
          app.translator.trans('fof-horizon.admin.stats.data.redis-used-memory'),
          redis_stats.memory_used,
          undefined,
          undefined,
          'memory_used_bytes'
        )}
        {redis_stats.memory_percentage !== null &&
          this.renderStat(
            app.translator.trans('fof-horizon.admin.stats.data.redis-memory-usage'),
            `${redis_stats.memory_percentage}%`,
            undefined,
            undefined,
            'memory_percentage'
          )}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.redis-peak-memory'), redis_stats.memory_peak)}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.redis-max-memory'), redis_stats.memory_max ?? 'auto')}
        {this.renderStat(
          app.translator.trans('fof-horizon.admin.stats.data.redis-memory-policy'),
          redis_stats.memory_max_policy,
          app.translator.trans('fof-horizon.admin.stats.data.redis-memory-policy-tooltip'),
          'https://redis.io/docs/latest/develop/reference/eviction/'
        )}

        {/* Redis Operational Stats */}
        {this.renderStat(
          app.translator.trans('fof-horizon.admin.stats.data.redis-ops-per-sec'),
          redis_stats.ops_per_sec?.toString() ?? '0',
          undefined,
          undefined,
          'ops_per_sec'
        )}
        {this.renderStat(
          app.translator.trans('fof-horizon.admin.stats.data.redis-connected-clients'),
          redis_stats.connected_clients?.toString() ?? '0'
        )}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.redis-blocked-clients'), redis_stats.blocked_clients?.toString() ?? '0')}

        {/* Job Stats with trends */}
        {this.renderStat(
          app.translator.trans('fof-horizon.admin.stats.data.jobs-per-minute'),
          jobsPerMinute?.toString() ?? '0',
          undefined,
          undefined,
          'jobsPerMinute'
        )}
        {this.renderStat(
          app.translator.trans('fof-horizon.admin.stats.data.jobs-past-hour'),
          recentJobs?.toString() ?? '0',
          undefined,
          undefined,
          'recentJobs'
        )}
        {this.renderStat(
          app.translator.trans('fof-horizon.admin.stats.data.failed-last-seconds'),
          recentlyFailed?.toString() ?? '0',
          undefined,
          undefined,
          'recentlyFailed'
        )}
        {this.renderStat(
          app.translator.trans('fof-horizon.admin.stats.data.total-processes'),
          processes?.toString() ?? '0',
          undefined,
          undefined,
          'processes'
        )}

        {/* Performance Metrics */}
        {this.renderStat(
          app.translator.trans('fof-horizon.admin.stats.data.max-wait-time'),
          maxWaitTime ? `${maxWaitTime}m` : '0m',
          maxWaitQueue ? app.translator.trans('fof-horizon.admin.stats.data.max-wait-queue', { queue: maxWaitQueue }) : undefined
        )}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.max-runtime'), queueWithMaxRuntime ?? '-')}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.max-throughput'), queueWithMaxThroughput ?? '-')}

        {/* Success/Failure Rates */}
        {successRate !== undefined &&
          this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.success-rate'), `${successRate}%`, undefined, undefined, 'successRate')}
        {failureRate !== undefined &&
          this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.failure-rate'), `${failureRate}%`, undefined, undefined, 'failureRate')}
      </>
    );
  }

  renderStat(
    label: NestedStringArray | string,
    value: string,
    infoLabel: NestedStringArray | string | undefined = undefined,
    infoUrl: string | undefined = undefined,
    trendKey: string | undefined = undefined
  ) {
    const trend = trendKey ? this.calculateTrend(trendKey) : null;
    const threshold = trendKey ? this.checkThreshold(trendKey, value) : null;

    let statClass = 'HorizonStatsWidget-stat';
    if (threshold) {
      statClass += ` HorizonStatsWidget-stat--${threshold}`;
    }

    return <div className={statClass}>{this.statItems(label, value, infoLabel, infoUrl, trend, threshold).toArray()}</div>;
  }

  statItems(
    label: NestedStringArray | string,
    value: string,
    infoLabel: NestedStringArray | string | undefined,
    infoUrl: string | undefined,
    trend: 'up' | 'down' | null,
    threshold: 'warning' | 'danger' | null
  ): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add('label', <small>{this.labelItems(label, infoLabel, infoUrl).toArray()}</small>, 100);

    items.add(
      'value',
      <p>
        {value || !this.loading ? value : <LoadingIndicator size="small" display="inline" />}
        {trend && (
          <span className={`HorizonStatsWidget-trend HorizonStatsWidget-trend--${trend}`}>
            {icon(trend === 'up' ? 'fas fa-arrow-up' : 'fas fa-arrow-down')}
          </span>
        )}
        {threshold && (
          <span className={`HorizonStatsWidget-threshold HorizonStatsWidget-threshold--${threshold}`}>
            {icon(threshold === 'danger' ? 'fas fa-exclamation-triangle' : 'fas fa-exclamation-circle')}
          </span>
        )}
      </p>,
      80
    );

    return items;
  }

  calculateTrend(key: string): 'up' | 'down' | null {
    if (!this.previousData || !this.previousData[key]) return null;

    const redis_stats_keys = ['memory_used_bytes', 'ops_per_sec', 'memory_percentage'];
    let currentValue: number;
    let previousValue: number;

    if (redis_stats_keys.includes(key)) {
      currentValue = this.data.redis_stats?.[key] ?? 0;
      previousValue = this.previousData.redis_stats?.[key] ?? 0;
    } else {
      currentValue = this.data[key] ?? 0;
      previousValue = this.previousData[key] ?? 0;
    }

    if (currentValue === previousValue) return null;
    return currentValue > previousValue ? 'up' : 'down';
  }

  checkThreshold(key: string, value: string): 'warning' | 'danger' | null {
    const numericValue = parseFloat(value);
    if (isNaN(numericValue)) return null;

    // Memory percentage thresholds
    if (key === 'memory_percentage') {
      if (numericValue >= 90) return 'danger';
      if (numericValue >= 75) return 'warning';
    }

    // Failure rate thresholds
    if (key === 'failureRate') {
      if (numericValue >= 20) return 'danger';
      if (numericValue >= 10) return 'warning';
    }

    // Failed jobs threshold
    if (key === 'recentlyFailed') {
      if (numericValue >= 50) return 'danger';
      if (numericValue >= 20) return 'warning';
    }

    // Process count threshold (0 processes is critical)
    if (key === 'processes') {
      if (numericValue === 0) return 'danger';
    }

    return null;
  }

  labelItems(
    label: NestedStringArray | string,
    infoLabel: NestedStringArray | string | undefined,
    infoUrl: string | undefined
  ): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add('label', <span>{label}</span>, 100);

    if (infoLabel && infoUrl) {
      items.add(
        'info',
        <Tooltip text={infoLabel}>
          <span>
            {' '}
            <LinkButton href={infoUrl} external={true} target="_blank" icon="fas fa-info-circle" />
          </span>
        </Tooltip>,
        90
      );
    }

    return items;
  }

  renderStatusIndicator(status: string | null) {
    const iconClass = status === 'running' ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-danger';

    return (
      <div className="HorizonStatsWidget-stat">
        <small>{app.translator.trans('fof-horizon.admin.stats.data.status.label')}</small>
        <p>
          {icon(iconClass)} {status ? app.translator.trans(`fof-horizon.admin.stats.data.status.${status}`) : ''}
        </p>
      </div>
    );
  }
}
