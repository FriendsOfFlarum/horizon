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

export default class HorizonStatsWidget extends DashboardWidget {
  loading = true;
  data: any = {};
  error: string | null = null;
  autoRefreshEnabled = false;
  autoRefreshInterval?: number;

  oncreate(vnode: Mithril.Vnode<IDashboardWidgetAttrs>) {
    super.oncreate(vnode);
    this.loadHorizonStats();
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

      this.data = data;
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
    if (enabled) {
      this.setAutoRefresh();
    } else {
      this.clearAutoRefresh();
    }
  }

  setAutoRefresh() {
    this.clearAutoRefresh();
    this.autoRefreshInterval = setInterval(() => this.loadHorizonStats(), 5000) as unknown as number;
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
          <h4 className="HorizonStatsWidget-title">{app.translator.trans('fof-horizon.admin.stats.heading')}</h4>
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
          <Switch state={this.autoRefreshEnabled} onchange={this.toggleAutoRefresh.bind(this)} loading={this.loading}>
            {app.translator.trans('fof-horizon.admin.stats.auto_refresh')}
          </Switch>
        </div>
      </div>
    );
  }

  renderStatsSection() {
    const { jobsPerMinute, recentJobs, recentlyFailed, status, processes, queueWithMaxRuntime, queueWithMaxThroughput } = this.data;
    const redis_stats = this.data.redis_stats ?? {};

    return (
      <>
        {this.renderStatusIndicator(status)}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.redis-used-memory'), redis_stats.memory_used)}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.redis-peak-memory'), redis_stats.memory_peak)}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.redis-max-memory'), redis_stats.memory_max ?? 'auto')}
        {this.renderStat(
          app.translator.trans('fof-horizon.admin.stats.data.redis-memory-policy'),
          redis_stats.memory_max_policy,
          app.translator.trans('fof-horizon.admin.stats.data.redis-memory-policy-tooltip'),
          'https://redis.io/docs/latest/develop/reference/eviction/'
        )}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.redis-ops-per-sec'), redis_stats.ops_per_sec?.toString() ?? '0')}
        {this.renderStat(
          app.translator.trans('fof-horizon.admin.stats.data.redis-connected-clients'),
          redis_stats.connected_clients?.toString() ?? '0'
        )}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.redis-blocked-clients'), redis_stats.blocked_clients?.toString() ?? '0')}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.jobs-per-minute'), jobsPerMinute?.toString() ?? '0')}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.jobs-past-hour'), recentJobs?.toString() ?? '0')}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.failed-last-seconds'), recentlyFailed?.toString() ?? '0')}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.total-processes'), processes?.toString() ?? '0')}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.max-wait-time'), '-')}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.max-runtime'), queueWithMaxRuntime ?? '-')}
        {this.renderStat(app.translator.trans('fof-horizon.admin.stats.data.max-throughput'), queueWithMaxThroughput ?? '-')}
      </>
    );
  }

  renderStat(label: Mithril.Children, value: string, infoLabel: Mithril.Children | undefined = undefined, infoUrl: string | undefined = undefined) {
    return <div className="HorizonStatsWidget-stat">{this.statItems(label, value, infoLabel, infoUrl).toArray()}</div>;
  }

  statItems(
    label: Mithril.Children,
    value: string,
    infoLabel: Mithril.Children | undefined,
    infoUrl: string | undefined
  ): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add('label', <small>{this.labelItems(label, infoLabel, infoUrl).toArray()}</small>, 100);

    items.add('value', <p>{value || !this.loading ? value : <LoadingIndicator size="small" display="inline" />}</p>, 80);

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
