import app from 'flarum/admin/app';
import DashboardWidget, { IDashboardWidgetAttrs } from 'flarum/admin/components/DashboardWidget';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Mithril from 'mithril';
import LinkButton from 'flarum/common/components/LinkButton';
import Tooltip from 'flarum/common/components/Tooltip';
import Switch from 'flarum/common/components/Switch';
import Icon from 'flarum/common/components/Icon';
import ItemList from 'flarum/common/utils/ItemList';
import humanTime from 'flarum/common/utils/humanTime';

export default class HorizonStatsWidget extends DashboardWidget {
  loading = true;
  data: any = {};
  previousData: any = {};
  error: string | null = null;
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
    this.error = null;
    m.redraw();

    try {
      const data = await app.request({
        method: 'GET',
        url: app.forum.attribute('adminUrl') + '/horizon/api/stats',
      });

      // Store previous data for trend comparison before updating
      if (this.data && !this.data.error && Object.keys(this.data).length > 0) {
        this.previousData = { ...this.data };
      }

      this.data = data;
      this.lastRefreshTime = Date.now();
      this.loading = false;
    } catch (error: any) {
      this.error = error?.response?.error || app.translator.trans('fof-horizon.admin.stats.error.fetch_failed');
      this.loading = false;
      this.clearAutoRefresh();
    }

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
    if (this.error) {
      return this.renderError();
    }

    return (
      <div className="HorizonStatsWidget-container">
        <div className="HorizonStatsWidget-header">
          <div className="HorizonStatsWidget-titleSection">
            <h4 className="HorizonStatsWidget-title">{app.translator.trans('fof-horizon.admin.stats.heading')}</h4>
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
              href={app.forum.attribute('adminUrl') + '/horizon'}
              target="_blank"
              external={true}
              disabled={this.error !== null}
            >
              {app.translator.trans('fof-horizon.admin.stats.full_dashboard')}
            </LinkButton>
          </div>
        </div>
        <div className="HorizonStatsWidget-grid">{this.renderStatsSection()}</div>
        <div className="HorizonStatsWidget-footer">
          <div className="HorizonStatsWidget-footerLeft">
            <Switch state={this.autoRefreshEnabled} onchange={this.toggleAutoRefresh.bind(this)} loading={this.loading}>
              {app.translator.trans('fof-horizon.admin.stats.auto_refresh')}
            </Switch>
            {this.autoRefreshEnabled && this.renderRefreshIntervalSelector()}
          </div>
          {this.renderHealthIndicator()}
        </div>
      </div>
    );
  }

  renderLastRefresh() {
    if (!this.lastRefreshTime) return null;

    const timeAgo = humanTime(this.lastRefreshTime);

    return (
      <small className="HorizonStatsWidget-lastRefresh">
        <Icon name="fas fa-clock" /> {app.translator.trans('fof-horizon.admin.stats.last_refresh', { time: timeAgo })}
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
          <Icon name="fas fa-heartbeat" /> {healthLabel}
        </div>
      </Tooltip>
    );
  }

  renderStatsSection() {
    const {
      jobsPerMinute,
      recentJobs,
      failedJobs,
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
          failedJobs?.toString() ?? '0',
          undefined,
          undefined,
          'failedJobs'
        )}
        {this.renderStat(
          app.translator.trans('fof-horizon.admin.stats.data.total-processes'),
          processes?.toString() ?? '0',
          undefined,
          undefined,
          'processes'
        )}
        {this.renderStat(
          app.translator.trans('fof-horizon.admin.stats.data.max-wait-time'),
          maxWaitTime ? `${maxWaitTime}m` : '0m',
          maxWaitQueue ? app.translator.trans('fof-horizon.admin.stats.data.max-wait-queue', { queue: maxWaitQueue }) : undefined
        )}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.max-runtime'), queueWithMaxRuntime ?? '-')}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.max-throughput'), queueWithMaxThroughput ?? '-')}
        {successRate !== undefined &&
          this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.success-rate'), `${successRate}%`, undefined, undefined, 'successRate')}
        {failureRate !== undefined &&
          this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.failure-rate'), `${failureRate}%`, undefined, undefined, 'failureRate')}
      </>
    );
  }

  renderStat(label: Mithril.Children, value: string, infoLabel?: Mithril.Children, infoUrl?: string, metricKey?: string) {
    return <div className="HorizonStatsWidget-stat">{this.statItems(label, value, infoLabel, infoUrl, metricKey).toArray()}</div>;
  }

  statItems(
    label: Mithril.Children,
    value: string,
    infoLabel: Mithril.Children | undefined,
    infoUrl: string | undefined,
    metricKey?: string
  ): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add('label', <small>{this.labelItems(label, infoLabel, infoUrl).toArray()}</small>, 100);

    const valueContent = [];

    // Add trend indicator if we have previous data
    if (metricKey && this.previousData[metricKey] !== undefined && this.data[metricKey] !== undefined) {
      const current = parseFloat(this.data[metricKey]);
      const previous = parseFloat(this.previousData[metricKey]);

      if (current > previous) {
        valueContent.push(<Icon name="fas fa-arrow-up" className="HorizonStatsWidget-trendUp" />);
      } else if (current < previous) {
        valueContent.push(<Icon name="fas fa-arrow-down" className="HorizonStatsWidget-trendDown" />);
      }
    }

    // Add threshold warnings
    if (metricKey === 'memory_percentage' && value) {
      const percentage = parseFloat(value);
      if (percentage > 90) {
        valueContent.push(<Icon name="fas fa-exclamation-triangle" className="HorizonStatsWidget-thresholdDanger" />);
      } else if (percentage > 75) {
        valueContent.push(<Icon name="fas fa-exclamation-circle" className="HorizonStatsWidget-thresholdWarning" />);
      }
    }

    valueContent.push(value || (!this.loading ? value : <LoadingIndicator size="small" display="inline" />));

    items.add('value', <p>{valueContent}</p>, 80);

    return items;
  }

  labelItems(label: Mithril.Children, infoLabel: Mithril.Children | undefined, infoUrl: string | undefined): ItemList<Mithril.Children> {
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
          <Icon name={iconClass} /> {status ? app.translator.trans(`fof-horizon.admin.stats.data.status.${status}`) : ''}
        </p>
      </div>
    );
  }

  renderError() {
    return (
      <div className="HorizonStatsWidget-error Alert Alert--danger">
        <div className="Alert-icon">
          <Icon name="fas fa-exclamation-triangle" />
        </div>
        <div className="Alert-content">
          <h4>{app.translator.trans('fof-horizon.admin.stats.error.title')}</h4>
          <p>{this.error}</p>
          <p>
            <a href="https://redis.io/docs/latest/operate/oss_and_stack/install/install-redis/" target="_blank" rel="noopener noreferrer">
              {app.translator.trans('fof-horizon.admin.stats.error.setup_docs')}
            </a>
          </p>
        </div>
      </div>
    );
  }
}
