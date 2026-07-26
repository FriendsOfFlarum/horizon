import app from 'flarum/admin/app';
import DashboardWidget, { IDashboardWidgetAttrs } from 'flarum/admin/components/DashboardWidget';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import Tooltip from 'flarum/common/components/Tooltip';
import Switch from 'flarum/common/components/Switch';
import Icon from 'flarum/common/components/Icon';
import humanTime from 'flarum/common/utils/humanTime';
import type Mithril from 'mithril';
import statsStore, { HealthFactor } from '../statsStore';
import { horizonUrl, periodLabel, statTile } from '../statsView';

const trans = (key: string, params = {}) => app.translator.trans(`fof-horizon.admin.stats.${key}`, params);

export default class HorizonQueueWidget extends DashboardWidget {
  oncreate(vnode: Mithril.VnodeDOM<IDashboardWidgetAttrs, this>) {
    super.oncreate(vnode);
    statsStore.attach();
  }

  onremove() {
    statsStore.detach();
  }

  className() {
    return 'HorizonWidget HorizonWidget--queue';
  }

  content() {
    const { data, error, loading } = statsStore;

    return (
      <div className="HorizonWidget-body">
        <div className="HorizonWidget-header">
          <h3 className="HorizonWidget-title">
            <Icon name="fas fa-stream" /> {trans('queue_heading')}
            {data && this.statusPill(data.status)}
            {data && this.healthPill(data.health)}
          </h3>
          <div className="HorizonWidget-controls">
            {statsStore.lastRefresh && <span className="HorizonWidget-lastRefresh">{humanTime(new Date(statsStore.lastRefresh))}</span>}
            <Tooltip text={trans('auto_refresh')}>
              <Switch state={statsStore.autoRefresh} onchange={(value: boolean) => statsStore.toggleAutoRefresh(value)} />
            </Tooltip>
            <Button
              className="Button Button--icon"
              icon="fas fa-sync-alt"
              loading={loading}
              onclick={() => statsStore.load()}
              aria-label={trans('refresh')}
            />
            <LinkButton
              className="Button"
              icon="fas fa-external-link-alt"
              href={app.forum.attribute('adminUrl') + '/horizon'}
              external={true}
              target="_blank"
            >
              {trans('full_dashboard')}
            </LinkButton>
          </div>
        </div>

        {error && <div className="HorizonWidget-error">{error}</div>}
        {!error && !data && <LoadingIndicator />}
        {!error && data && this.tiles(data)}
      </div>
    );
  }

  tiles(data: any) {
    return (
      <div className="HorizonWidget-tiles">
        {statTile('processes', data.processes)}
        {statTile('jobs-per-minute', data.jobsPerMinute)}
        {statTile('pending-jobs', data.pendingJobs, undefined, horizonUrl('/jobs/pending'))}
        {statTile('recent-jobs', data.recentJobs, periodLabel(data.periods?.recentJobs), horizonUrl('/jobs/completed'))}
        {statTile('failed-jobs', data.failedJobs, periodLabel(data.periods?.failedJobs), horizonUrl('/failed'))}
        {statTile('max-wait', data.maxWaitTime ? `${Math.round(data.maxWaitTime)} min` : '0 min', data.maxWaitQueue)}
        {data.busiestQueues?.slowestQueue && statTile('slowest-queue', data.busiestQueues.slowestQueue)}
        {data.busiestQueues?.highestThroughputQueue && statTile('highest-throughput-queue', data.busiestQueues.highestThroughputQueue)}
      </div>
    );
  }

  statusPill(status: string) {
    return <span className={`HorizonWidget-pill HorizonWidget-pill--${status}`}>{trans(`status.${status}`)}</span>;
  }

  healthPill(health: { score: number; factors: HealthFactor[] }) {
    if (!health) return null;

    const level = health.score >= 90 ? 'excellent' : health.score >= 70 ? 'good' : health.score >= 40 ? 'poor' : 'critical';

    const tooltip = [
      trans('health.tooltip_heading', { score: health.score }),
      ...health.factors.map((factor) => `${trans(`health.factor.${factor.key}`, { value: factor.value })} (${factor.impact})`),
    ].join(' — ');

    return (
      <Tooltip text={tooltip}>
        <span className={`HorizonWidget-pill HorizonWidget-pill--health-${level}`}>{trans(`health.${level}`)}</span>
      </Tooltip>
    );
  }
}
